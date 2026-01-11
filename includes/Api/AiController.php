<?php
namespace PersonaliseIt\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AiController extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'personaliseit/v1';
        $this->rest_base = 'ai';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'generate_image' ],
            'permission_callback' => [ $this, 'permissions_check' ],
            'args'                => [
                'prompt' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/style', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'style_image' ],
            'permission_callback' => [ $this, 'permissions_check' ],
            'args'                => [
                'image' => [
                    'required' => true,
                    'type'     => 'string', // Base64 or URL
                ],
                'prompt' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'remove_background' => [
                    'required' => false,
                    'type'     => 'boolean',
                    'default'  => false,
                ],
            ],
        ] );
        
        // Endpoint to get models is likely needed for settings page? 
        // Or user just types it? User said "let the user select a image generation model".
        // A list would be nice, but dynamic from OpenRouter is best.
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/models', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_models' ],
            'permission_callback' => '__return_true', // Public? Or admin only? Admin only for settings.
        ] );
    }

    public function permissions_check( $request ) {
        // 1. Check if AI features are enabled globally
        $enabled_gen = get_option( 'personaliseit_enable_ai_generate', false );
        $enabled_style = get_option( 'personaliseit_enable_ai_style', false );

        if ( ! $enabled_gen && ! $enabled_style ) {
             return new WP_Error( 'ai_disabled', 'AI features are disabled.', [ 'status' => 403 ] );
        }

        // 2. Security: Verify Nonce
        // Allow logged-in users automatically via cookie (WP Internal)
        if ( is_user_logged_in() && current_user_can( 'read' ) ) {
             return true;
        }

        // For guests/frontend JS, verify the X-WP-Nonce header
        $nonce = $request->get_header( 'x_wp_nonce' );
        if ( ! $nonce ) {
             // Fallback: check query param
             $nonce = $request->get_param( '_wpnonce' );
        }

        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
             return new WP_Error( 'rest_forbidden', __( 'Invalid or missing nonce.', 'personaliseit' ), [ 'status' => 401 ] );
        }

        return true; 
    }

    public function generate_image( $request ) {
        $api_key = get_option( 'personaliseit_openrouter_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'Server missing API Key', [ 'status' => 500 ] );
        }

        $prompt = sanitize_text_field( $request->get_param( 'prompt' ) );
        return $this->_generate_image_internal( $prompt, $api_key );
    }

    public function style_image( $request ) {
        try {
            $api_key = get_option( 'personaliseit_openrouter_api_key' );
            if ( empty( $api_key ) ) {
                return new WP_Error( 'no_api_key', 'OpenRouter API Key not configured. Go to Settings > AI Integration.', [ 'status' => 500 ] );
            }

            $image_url = sanitize_text_field( $request->get_param( 'image' ) );
            $style_prompt = sanitize_text_field( $request->get_param( 'prompt' ) );
            $remove_bg = $request->get_param( 'remove_background' );

            if ( empty( $image_url ) ) {
                return new WP_Error( 'missing_image', 'No image provided for styling.', [ 'status' => 400 ] );
            }

            // HANDLE LOCALHOST/PRIVATE IMAGES:
            // OpenRouter cannot access 'localhost' or private IPs.
            // We must convert the local image URL to a Base64 Data URI to send the actual image data.
            $image_payload = $this->_prepare_image_payload( $image_url );

            // DIRECT STYLE TRANSFER (Image + Prompt -> Image)
            $final_prompt = $style_prompt;
            
            // Step 1: Generate the Styled Image
            $gen_res = $this->_generate_image_internal( $final_prompt, $api_key, $image_payload );
            
            if ( is_wp_error( $gen_res ) ) {
                return new WP_Error( 'gen_error', 'Style Transfer Failed: ' . $gen_res->get_error_message(), [ 'status' => 500 ] );
            }
            
            return $gen_res;
        } catch ( \Exception $e ) {
            return new WP_Error( 'style_exception', 'Style Transfer Error: ' . $e->getMessage(), [ 'status' => 500 ] );
        } catch ( \Throwable $e ) {
            return new WP_Error( 'style_fatal', 'Style Transfer Fatal Error: ' . $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    private function _generate_image_internal( $prompt, $api_key, $image_input = null ) {
        // Use Chat Completions endpoint which is the unified standard on OpenRouter
        // Use the model selected in settings, default to google/gemini-2.5-flash-image if not set
        $model = get_option( 'personaliseit_ai_model', 'google/gemini-2.5-flash-image' );
        
        // Remove aggressive hotfix overrides - TRUST THE USER SETTING
        // User asked "what is the point of choosing a model in the settings?"
        // So we should respect it.
        
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        
        $content_payload = [];
        
        // 1. Add Text Prompt
        $content_payload[] = [
            'type' => 'text',
            'text' => $prompt
        ];

        // 2. Add Image if provided (Multimodal Input)
        if ( $image_input ) {
            $content_payload[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image_input
                ]
            ];
        }

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content_payload
                ]
            ],
            // REQUIRED for OpenRouter Image Gen models
            'modalities' => [ 'image', 'text' ],
            'stream' => false,
        ];

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Referer'       => get_site_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ],
            'body'    => json_encode( $body ),
            'timeout' => 120,
            // SSL verification enabled for security (MITM protection)
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( isset( $data['error'] ) ) {
            // Safely extract error message from various possible structures
            $error_msg = 'Unknown OpenRouter error';
            if ( is_array( $data['error'] ) ) {
                $error_msg = $data['error']['message'] ?? $data['error']['code'] ?? json_encode( $data['error'] );
            } elseif ( is_string( $data['error'] ) ) {
                $error_msg = $data['error'];
            }
            return new WP_Error( 'openrouter_error', $error_msg, [ 'status' => 400 ] );
        }

        // 1. Check for Structured Image Response (OpenRouter Standard) or Parse Content
        $final_url = $this->_parse_generation_response( $data );

        if ( $final_url ) {
             // SAVE LOCALLY to fix CORS for client-side manipulation
             // Ensure download_url function is available (it's in admin includes)
             if ( ! function_exists( 'download_url' ) ) {
                 require_once ABSPATH . 'wp-admin/includes/file.php';
             }
             $temp_file = download_url( $final_url );
             if ( ! is_wp_error( $temp_file ) ) {
                 $file_array = [
                     'name'     => 'ai_gen_' . uniqid() . '.png', // Force PNG extension or detect?
                     'tmp_name' => $temp_file
                 ];
                 
                 // Check actual mime type
                 $mime = mime_content_type( $temp_file );
                 $ext = 'jpg';
                 if ($mime === 'image/png') $ext = 'png';
                 if ($mime === 'image/webp') $ext = 'webp';
                 
                 $filename = 'ai_gen_' . uniqid() . '.' . $ext;
                 $upload = wp_upload_bits( $filename, null, file_get_contents( $temp_file ) );
                 @unlink( $temp_file );
                 
                 if ( ! $upload['error'] ) {
                     return rest_ensure_response( [ 'url' => $upload['url'] ] );
                 }
             }
             
             // Fallback to remote URL if download fails (might have CORS issues)
             return rest_ensure_response( [ 'url' => $final_url ] );
        }
        
        // Return full debug if parsing failed
        $code = wp_remote_retrieve_response_code( $response );
        $short_body = substr($response_body, 0, 500);
        return new WP_Error( 'no_image', "DEBUG: Status: $code. Parsed Content: " . substr($content ?? '', 0, 100) . ". Body: $short_body", [ 'status' => 500 ] );
    }

    public function get_models( $request ) {
        // Fetch models from OpenRouter to let user pick
        $response = wp_remote_get( 'https://openrouter.ai/api/v1/models', [
            'timeout' => 60,
            'headers' => [
                'Referer' => get_site_url(),
                'X-Title' => get_bloginfo( 'name' ),
            ]
        ] );

        if ( is_wp_error( $response ) ) {
            return $response; // Return the actual error to the frontend
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data || ! isset( $data['data'] ) ) {
             // If we can't parse, or data structure is wrong
             return new WP_Error( 'openrouter_bad_response', 'Invalid response from OpenRouter: ' . substr($body, 0, 100), [ 'status' => 500 ] );
        }
        
        $models = [];
        foreach ( $data['data'] as $model ) {
             $id = strtolower( $model['id'] );
             $name = strtolower( $model['name'] );
             $modality = $model['architecture']['modality'] ?? '';
             
             $is_valid = false;

             // 1. Strict Modality Check (Preferred)
             // We need Image Input AND Image Output (e.g., "text+image->image")
             if ( ! empty( $modality ) ) {
                 $parts = explode( '->', $modality );
                 if ( count( $parts ) === 2 ) {
                     $inputs = $parts[0];
                     $outputs = $parts[1];
                     // Must accept image (for Style Transfer) and output image (for Generation)
                     if ( strpos( $inputs, 'image' ) !== false && strpos( $outputs, 'image' ) !== false ) {
                         $is_valid = true;
                     }
                 }
             } 
             // 2. Fallback Heuristic if architecture missing, or specifically known models
             else {
                 // Known Img2Img capable families that might define modality differently or be missing it
                 if ( 
                     strpos( $id, 'flux' ) !== false || 
                     strpos( $id, 'stable-diffusion' ) !== false ||
                     strpos( $id, 'sdxl' ) !== false ||
                     ( strpos( $id, 'gemini' ) !== false && strpos( $id, 'image' ) !== false ) // Gemini Image models usually multimodal
                 ) {
                     $is_valid = true;
                 }
             }

             if ( $is_valid ) {
                 $models[] = [ 'id' => $model['id'], 'name' => $model['name'] ];
             }
        }

        // Fallback: If strict filtering returns nothing, maybe just return top 50 models purely for debugging or allow user to type?
        // But for now, returning empty array is valid if no image models found. 
        // We will return filtered list.
        return rest_ensure_response( $models );
    }

    private function _prepare_image_payload( $image_url ) {
        $upload_dir = wp_upload_dir();
        // Check if image is in our uploads directory
        if ( strpos( $image_url, $upload_dir['baseurl'] ) !== false ) {
            $local_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );
            if ( file_exists( $local_path ) ) {
                
                // Resize image to max 1024x1024 to reduce payload size
                $image_data = false;
                $editor = wp_get_image_editor( $local_path );
                if ( ! is_wp_error( $editor ) ) {
                    $editor->resize( 1024, 1024, false );
                    $temp_file = $editor->generate_filename( 'vision_temp' );
                    $saved = $editor->save( $temp_file );
                    if ( ! is_wp_error( $saved ) && file_exists( $saved['path'] ) ) {
                        $image_data = file_get_contents( $saved['path'] );
                        @unlink( $saved['path'] ); // Cleanup temp file
                        $local_path = $saved['path']; // Update path reference for mime type (though mime likely same)
                    }
                }

                // Fallback to original if resize failed or data empty
                if ( ! $image_data ) {
                     $image_data = file_get_contents( $local_path );
                }

                if ( $image_data !== false ) {
                    $mime_type = mime_content_type( $local_path );
                    // Fallback mime if unlinked
                    if (!$mime_type) $mime_type = 'image/jpeg'; 
                    
                    $base64 = base64_encode( $image_data );
                    return 'data:' . $mime_type . ';base64,' . $base64;
                }
            }
        }
        return $image_url; // Return original URL if remote or processing failed
    }

    private function _parse_generation_response( $data ) {
        // 1. Check for Structured Image Response (OpenRouter Standard)
        // Structure: choices[0].message.images[0].image_url.url
        if ( ! empty( $data['choices'][0]['message']['images'] ) ) {
             $images = $data['choices'][0]['message']['images'];
             if ( isset( $images[0]['image_url']['url'] ) ) {
                 return $images[0]['image_url']['url'];
             }
        }

        // 2. Parse Chat Content for URL (Markdown or Raw) if structured failed
        $content = $data['choices'][0]['message']['content'] ?? '';
        if ( preg_match( '/(https?:\/\/[^\s\)]+\.(?:png|jpg|jpeg|webp|gif))/i', $content, $matches ) ) {
            return $matches[1];
        } else if ( preg_match( '/\((https?:\/\/[^\s\)]+)\)/', $content, $matches ) ) {
            return $matches[1];
        }
        
        return false;
    }
}
