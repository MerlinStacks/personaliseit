<?php
namespace PersonaliseIt\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
    public function __construct() {
        add_action('init', [$this, 'register_settings']);
    }

    public function register_settings() {
        register_setting('personaliseit_settings_group', 'personaliseit_canvas_width', [
            'type' => 'integer',
            'default' => 800,
            'show_in_rest' => true,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_canvas_height', [
            'type' => 'integer',
            'default' => 800,
            'show_in_rest' => true,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_max_upload_size', [
            'type' => 'integer',
            'default' => 5, // MB
            'show_in_rest' => true,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_show_cost', [
            'type' => 'boolean',
            'default' => true,
            'show_in_rest' => true,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_label_position', [
            'type' => 'string',
            'default' => 'above', // above, below, left, right
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
        ]);

        // Personalisation Methods
        $methods = ['engraving', 'embroidery', 'dtf', 'uv', 'sublimation'];
        foreach ($methods as $method) {
            register_setting('personaliseit_settings_group', 'personaliseit_enable_' . $method, [
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest' => true,
            ]);
        }

        // Ready to Print Download Formats
        $formats = ['pdf', 'svg', 'jpg', 'png'];
        foreach ($formats as $format) {
            register_setting('personaliseit_settings_group', 'personaliseit_enable_' . $format . '_download', [
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest' => true,
            ]);
        }

        // AI Settings
        // Security: API keys must NOT be exposed via REST API
        register_setting('personaliseit_settings_group', 'personaliseit_openrouter_api_key', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_ai_model', [
            'type' => 'string',
            'default' => 'google/gemini-2.5-flash-image', // Update default to our preferred model
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_ai_style_prompt', [
            'type' => 'string',
            'default' => 'Make it look like a cartoon', // Default example
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
        ]);
         register_setting('personaliseit_settings_group', 'personaliseit_enable_ai_generate', [
            'type' => 'boolean',
            'default' => false,
            'show_in_rest' => true,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_enable_ai_style', [
            'type' => 'boolean',
            'default' => false,
            'show_in_rest' => true,
        ]);

        // Spotify Settings
        register_setting('personaliseit_settings_group', 'personaliseit_enable_spotify', [
            'type' => 'boolean',
            'default' => false,
            'show_in_rest' => true,
        ]);
        register_setting('personaliseit_settings_group', 'personaliseit_spotify_client_id', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
        ]);
        // Security: Client secrets must NOT be exposed via REST API
        register_setting('personaliseit_settings_group', 'personaliseit_spotify_client_secret', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false,
        ]);
        // Face Cutout Settings - runs locally, no API key needed
        register_setting('personaliseit_settings_group', 'personaliseit_enable_face_cutout', [
            'type' => 'boolean',
            'default' => false,
            'show_in_rest' => true,
        ]);

        register_setting('personaliseit_settings_group', 'personaliseit_ai_styles', [
            'type' => 'array',
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label'  => [ 'type' => 'string' ],
                            'prompt' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
            'default' => [],
            'sanitize_callback' => function( $styles ) {
                if ( ! is_array( $styles ) ) return [];
                $clean = [];
                foreach ( $styles as $style ) {
                    if ( isset( $style['label'], $style['prompt'] ) ) {
                        $clean[] = [
                            'label' => sanitize_text_field( $style['label'] ),
                            'prompt' => sanitize_text_field( $style['prompt'] ),
                        ];
                    }
                }
                return $clean;
            }
        ]);
    }
}
