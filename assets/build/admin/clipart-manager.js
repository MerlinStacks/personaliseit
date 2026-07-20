/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/imagetracerjs/imagetracer_v1.2.6.js"
/*!**********************************************************!*\
  !*** ./node_modules/imagetracerjs/imagetracer_v1.2.6.js ***!
  \**********************************************************/
(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_RESULT__;/*
	imagetracer.js version 1.2.6
	Simple raster image tracer and vectorizer written in JavaScript.
	andras@jankovics.net
*/

/*

The Unlicense / PUBLIC DOMAIN

This is free and unencumbered software released into the public domain.

Anyone is free to copy, modify, publish, use, compile, sell, or
distribute this software, either in source code form or as a compiled
binary, for any purpose, commercial or non-commercial, and by any
means.

In jurisdictions that recognize copyright laws, the author or authors
of this software dedicate any and all copyright interest in the
software to the public domain. We make this dedication for the benefit
of the public at large and to the detriment of our heirs and
successors. We intend this dedication to be an overt act of
relinquishment in perpetuity of all present and future rights to this
software under copyright law.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.

For more information, please refer to http://unlicense.org/

*/

(function(){ 'use strict';

function ImageTracer(){
	var _this = this;

	this.versionnumber = '1.2.6',
	
	////////////////////////////////////////////////////////////
	//
	//  API
	//
	////////////////////////////////////////////////////////////
	
	// Loading an image from a URL, tracing when loaded,
	// then executing callback with the scaled svg string as argument
	this.imageToSVG = function( url, callback, options ){
		options = _this.checkoptions(options);
		// loading image, tracing and callback
		_this.loadImage(
			url,
			function(canvas){
				callback(
					_this.imagedataToSVG( _this.getImgdata(canvas), options )
				);
			},
			options
		);
	},// End of imageToSVG()
	
	// Tracing imagedata, then returning the scaled svg string
	this.imagedataToSVG = function( imgd, options ){
		options = _this.checkoptions(options);
		// tracing imagedata
		var td = _this.imagedataToTracedata( imgd, options );
		// returning SVG string
		return _this.getsvgstring(td, options);
	},// End of imagedataToSVG()
	
	// Loading an image from a URL, tracing when loaded,
	// then executing callback with tracedata as argument
	this.imageToTracedata = function( url, callback, options ){
		options = _this.checkoptions(options);
		// loading image, tracing and callback
		_this.loadImage(
				url,
				function(canvas){
					callback(
						_this.imagedataToTracedata( _this.getImgdata(canvas), options )
					);
				},
				options
		);
	},// End of imageToTracedata()
	
	// Tracing imagedata, then returning tracedata (layers with paths, palette, image size)
	this.imagedataToTracedata = function( imgd, options ){
		options = _this.checkoptions(options);
		
		// 1. Color quantization
		var ii = _this.colorquantization( imgd, options );
		
		if(options.layering === 0){// Sequential layering
			
			// create tracedata object
			var tracedata = {
				layers : [],
				palette : ii.palette,
				width : ii.array[0].length-2,
				height : ii.array.length-2
			};
			
			// Loop to trace each color layer
			for(var colornum=0; colornum<ii.palette.length; colornum++){
				
				// layeringstep -> pathscan -> internodes -> batchtracepaths
				var tracedlayer =
					_this.batchtracepaths(
							
						_this.internodes(
								
							_this.pathscan(
								_this.layeringstep( ii, colornum ),
								options.pathomit
							),
							
							options
							
						),
						
						options.ltres,
						options.qtres
						
					);
				
				// adding traced layer
				tracedata.layers.push(tracedlayer);
				
			}// End of color loop
			
		}else{// Parallel layering
			// 2. Layer separation and edge detection
			var ls = _this.layering( ii );
			
			// Optional edge node visualization
			if(options.layercontainerid){ _this.drawLayers( ls, _this.specpalette, options.scale, options.layercontainerid ); }
			
			// 3. Batch pathscan
			var bps = _this.batchpathscan( ls, options.pathomit );
			
			// 4. Batch interpollation
			var bis = _this.batchinternodes( bps, options );
			
			// 5. Batch tracing and creating tracedata object
			var tracedata = {
				layers : _this.batchtracelayers( bis, options.ltres, options.qtres ),
				palette : ii.palette,
				width : imgd.width,
				height : imgd.height
			};
			
		}// End of parallel layering
		
		// return tracedata
		return tracedata;
		
	},// End of imagedataToTracedata()
	
	this.optionpresets = {
		'default': {
			
			// Tracing
			corsenabled : false,
			ltres : 1,
			qtres : 1,
			pathomit : 8,
			rightangleenhance : true,
			
			// Color quantization
			colorsampling : 2,
			numberofcolors : 16,
			mincolorratio : 0,
			colorquantcycles : 3,
			
			// Layering method
			layering : 0,
			
			// SVG rendering
			strokewidth : 1,
			linefilter : false,
			scale : 1,
			roundcoords : 1,
			viewbox : false,
			desc : false,
			lcpr : 0,
			qcpr : 0,
			
			// Blur
			blurradius : 0,
			blurdelta : 20
			
		},
		'posterized1': { colorsampling:0, numberofcolors:2 },
		'posterized2': { numberofcolors:4, blurradius:5 },
		'curvy': { ltres:0.01, linefilter:true, rightangleenhance:false },
		'sharp': { qtres:0.01, linefilter:false },
		'detailed': { pathomit:0, roundcoords:2, ltres:0.5, qtres:0.5, numberofcolors:64 },
		'smoothed': { blurradius:5, blurdelta: 64 },
		'grayscale': { colorsampling:0, colorquantcycles:1, numberofcolors:7 },
		'fixedpalette': { colorsampling:0, colorquantcycles:1, numberofcolors:27 },
		'randomsampling1': { colorsampling:1, numberofcolors:8 },
		'randomsampling2': { colorsampling:1, numberofcolors:64 },
		'artistic1': { colorsampling:0, colorquantcycles:1, pathomit:0, blurradius:5, blurdelta: 64, ltres:0.01, linefilter:true, numberofcolors:16, strokewidth:2 },
		'artistic2': { qtres:0.01, colorsampling:0, colorquantcycles:1, numberofcolors:4, strokewidth:0 },
		'artistic3': { qtres:10, ltres:10, numberofcolors:8 },
		'artistic4': { qtres:10, ltres:10, numberofcolors:64, blurradius:5, blurdelta: 256, strokewidth:2 },
		'posterized3': { ltres: 1, qtres: 1, pathomit: 20, rightangleenhance: true, colorsampling: 0, numberofcolors: 3,
			mincolorratio: 0, colorquantcycles: 3, blurradius: 3, blurdelta: 20, strokewidth: 0, linefilter: false,
			roundcoords: 1, pal: [ { r: 0, g: 0, b: 100, a: 255 }, { r: 255, g: 255, b: 255, a: 255 } ] }
	},// End of optionpresets
	
	// creating options object, setting defaults for missing values
	this.checkoptions = function(options){
		options = options || {};
		// Option preset
		if(typeof options === 'string'){
			options = options.toLowerCase();
			if( _this.optionpresets[options] ){ options = _this.optionpresets[options]; }else{ options = {}; }
		}
		// Defaults
		var ok = Object.keys(_this.optionpresets['default']);
		for(var k=0; k<ok.length; k++){
			if(!options.hasOwnProperty(ok[k])){ options[ok[k]] = _this.optionpresets['default'][ok[k]]; }
		}
		// options.pal is not defined here, the custom palette should be added externally: options.pal = [ { 'r':0, 'g':0, 'b':0, 'a':255 }, {...}, ... ];
		// options.layercontainerid is not defined here, can be added externally: options.layercontainerid = 'mydiv'; ... <div id="mydiv"></div>
		return options;
	},// End of checkoptions()
	
	////////////////////////////////////////////////////////////
	//
	//  Vectorizing functions
	//
	////////////////////////////////////////////////////////////
	
	// 1. Color quantization
	// Using a form of k-means clustering repeatead options.colorquantcycles times. http://en.wikipedia.org/wiki/Color_quantization
	this.colorquantization = function( imgd, options ){
		var arr = [], idx=0, cd,cdl,ci, paletteacc = [], pixelnum = imgd.width * imgd.height, i, j, k, cnt, palette;
		
		// imgd.data must be RGBA, not just RGB
		if( imgd.data.length < pixelnum * 4 ){
			var newimgddata = new Uint8ClampedArray(pixelnum * 4);
			for(var pxcnt = 0; pxcnt < pixelnum ; pxcnt++){
				newimgddata[pxcnt*4  ] = imgd.data[pxcnt*3  ];
				newimgddata[pxcnt*4+1] = imgd.data[pxcnt*3+1];
				newimgddata[pxcnt*4+2] = imgd.data[pxcnt*3+2];
				newimgddata[pxcnt*4+3] = 255;
			}
			imgd.data = newimgddata;
		}// End of RGBA imgd.data check
		
		// Filling arr (color index array) with -1
		for( j=0; j<imgd.height+2; j++ ){ arr[j]=[]; for(i=0; i<imgd.width+2 ; i++){ arr[j][i] = -1; } }
		
		// Use custom palette if pal is defined or sample / generate custom length palette
		if(options.pal){
			palette = options.pal;
		}else if(options.colorsampling === 0){
			palette = _this.generatepalette(options.numberofcolors);
		}else if(options.colorsampling === 1){
			palette = _this.samplepalette( options.numberofcolors, imgd );
		}else{
			palette = _this.samplepalette2( options.numberofcolors, imgd );
		}
		
		// Selective Gaussian blur preprocessing
		if( options.blurradius > 0 ){ imgd = _this.blur( imgd, options.blurradius, options.blurdelta ); }
		
		// Repeat clustering step options.colorquantcycles times
		for( cnt=0; cnt < options.colorquantcycles; cnt++ ){
			
			// Average colors from the second iteration
			if(cnt>0){
				// averaging paletteacc for palette
				for( k=0; k < palette.length; k++ ){
					
					// averaging
					if( paletteacc[k].n > 0 ){
						palette[k] = {  r: Math.floor( paletteacc[k].r / paletteacc[k].n ),
										g: Math.floor( paletteacc[k].g / paletteacc[k].n ),
										b: Math.floor( paletteacc[k].b / paletteacc[k].n ),
										a:  Math.floor( paletteacc[k].a / paletteacc[k].n ) };
					}
					
					// Randomizing a color, if there are too few pixels and there will be a new cycle
					if( ( paletteacc[k].n/pixelnum < options.mincolorratio ) && ( cnt < options.colorquantcycles-1 ) ){
						palette[k] = {  r: Math.floor(Math.random()*255),
										g: Math.floor(Math.random()*255),
										b: Math.floor(Math.random()*255),
										a: Math.floor(Math.random()*255) };
					}
					
				}// End of palette loop
			}// End of Average colors from the second iteration
			
			// Reseting palette accumulator for averaging
			for( i=0; i < palette.length; i++ ){ paletteacc[i] = { r:0, g:0, b:0, a:0, n:0 }; }
			
			// loop through all pixels
			for( j=0; j < imgd.height; j++ ){
				for( i=0; i < imgd.width; i++ ){
					
					// pixel index
					idx = (j*imgd.width+i)*4;
					
					// find closest color from palette by measuring (rectilinear) color distance between this pixel and all palette colors
					ci=0; cdl = 1024; // 4 * 256 is the maximum RGBA distance
					for( k=0; k<palette.length; k++ ){
						
						// In my experience, https://en.wikipedia.org/wiki/Rectilinear_distance works better than https://en.wikipedia.org/wiki/Euclidean_distance
						cd = Math.abs(palette[k].r-imgd.data[idx]) + Math.abs(palette[k].g-imgd.data[idx+1]) + Math.abs(palette[k].b-imgd.data[idx+2]) + Math.abs(palette[k].a-imgd.data[idx+3]);
						
						// Remember this color if this is the closest yet
						if(cd<cdl){ cdl = cd; ci = k; }
						
					}// End of palette loop
					
					// add to palettacc
					paletteacc[ci].r += imgd.data[idx  ];
					paletteacc[ci].g += imgd.data[idx+1];
					paletteacc[ci].b += imgd.data[idx+2];
					paletteacc[ci].a += imgd.data[idx+3];
					paletteacc[ci].n++;
					
					// update the indexed color array
					arr[j+1][i+1] = ci;
					
				}// End of i loop
			}// End of j loop
			
		}// End of Repeat clustering step options.colorquantcycles times
		
		return { array:arr, palette:palette };
		
	},// End of colorquantization()
	
	// Sampling a palette from imagedata
	this.samplepalette = function( numberofcolors, imgd ){
		var idx, palette=[];
		for(var i=0; i<numberofcolors; i++){
			idx = Math.floor( Math.random() * imgd.data.length / 4 ) * 4;
			palette.push({ r:imgd.data[idx  ], g:imgd.data[idx+1], b:imgd.data[idx+2], a:imgd.data[idx+3] });
		}
		return palette;
	},// End of samplepalette()
	
	// Deterministic sampling a palette from imagedata: rectangular grid
	this.samplepalette2 = function( numberofcolors, imgd ){
		var idx, palette=[], ni = Math.ceil(Math.sqrt(numberofcolors)), nj = Math.ceil(numberofcolors/ni),
			vx = imgd.width / (ni+1), vy = imgd.height / (nj+1);
		for(var j=0; j<nj; j++){
			for(var i=0; i<ni; i++){
				if(palette.length === numberofcolors){
					break;
				}else{
					idx = Math.floor( ((j+1)*vy) * imgd.width + ((i+1)*vx) ) * 4;
					palette.push( { r:imgd.data[idx], g:imgd.data[idx+1], b:imgd.data[idx+2], a:imgd.data[idx+3] } );
				}
			}
		}
		return palette;
	},// End of samplepalette2()
	
	// Generating a palette with numberofcolors
	this.generatepalette = function(numberofcolors){
		var palette = [], rcnt, gcnt, bcnt;
		if(numberofcolors<8){
			
			// Grayscale
			var graystep = Math.floor(255/(numberofcolors-1));
			for(var i=0; i<numberofcolors; i++){ palette.push({ r:i*graystep, g:i*graystep, b:i*graystep, a:255 }); }
			
		}else{
			
			// RGB color cube
			var colorqnum = Math.floor(Math.pow(numberofcolors, 1/3)), // Number of points on each edge on the RGB color cube
				colorstep = Math.floor(255/(colorqnum-1)), // distance between points
				rndnum = numberofcolors - colorqnum*colorqnum*colorqnum; // number of random colors
			
			for(rcnt=0; rcnt<colorqnum; rcnt++){
				for(gcnt=0; gcnt<colorqnum; gcnt++){
					for(bcnt=0; bcnt<colorqnum; bcnt++){
						palette.push( { r:rcnt*colorstep, g:gcnt*colorstep, b:bcnt*colorstep, a:255 } );
					}// End of blue loop
				}// End of green loop
			}// End of red loop
			
			// Rest is random
			for(rcnt=0; rcnt<rndnum; rcnt++){ palette.push({ r:Math.floor(Math.random()*255), g:Math.floor(Math.random()*255), b:Math.floor(Math.random()*255), a:Math.floor(Math.random()*255) }); }

		}// End of numberofcolors check
		
		return palette;
	},// End of generatepalette()
		
	// 2. Layer separation and edge detection
	// Edge node types ( ▓: this layer or 1; ░: not this layer or 0 )
	// 12  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓
	// 48  ░░  ░░  ░░  ░░  ░▓  ░▓  ░▓  ░▓  ▓░  ▓░  ▓░  ▓░  ▓▓  ▓▓  ▓▓  ▓▓
	//     0   1   2   3   4   5   6   7   8   9   10  11  12  13  14  15
	this.layering = function(ii){
		// Creating layers for each indexed color in arr
		var layers = [], val=0, ah = ii.array.length, aw = ii.array[0].length, n1,n2,n3,n4,n5,n6,n7,n8, i, j, k;
		
		// Create layers
		for(k=0; k<ii.palette.length; k++){
			layers[k] = [];
			for(j=0; j<ah; j++){
				layers[k][j] = [];
				for(i=0; i<aw; i++){
					layers[k][j][i]=0;
				}
			}
		}
		
		// Looping through all pixels and calculating edge node type
		for(j=1; j<ah-1; j++){
			for(i=1; i<aw-1; i++){
				
				// This pixel's indexed color
				val = ii.array[j][i];
				
				// Are neighbor pixel colors the same?
				n1 = ii.array[j-1][i-1]===val ? 1 : 0;
				n2 = ii.array[j-1][i  ]===val ? 1 : 0;
				n3 = ii.array[j-1][i+1]===val ? 1 : 0;
				n4 = ii.array[j  ][i-1]===val ? 1 : 0;
				n5 = ii.array[j  ][i+1]===val ? 1 : 0;
				n6 = ii.array[j+1][i-1]===val ? 1 : 0;
				n7 = ii.array[j+1][i  ]===val ? 1 : 0;
				n8 = ii.array[j+1][i+1]===val ? 1 : 0;
				
				// this pixel's type and looking back on previous pixels
				layers[val][j+1][i+1] = 1 + n5 * 2 + n8 * 4 + n7 * 8 ;
				if(!n4){ layers[val][j+1][i  ] = 0 + 2 + n7 * 4 + n6 * 8 ; }
				if(!n2){ layers[val][j  ][i+1] = 0 + n3*2 + n5 * 4 + 8 ; }
				if(!n1){ layers[val][j  ][i  ] = 0 + n2*2 + 4 + n4 * 8 ; }
				
			}// End of i loop
		}// End of j loop
		
		return layers;
	},// End of layering()
	
	// 2. Layer separation and edge detection
	// Edge node types ( ▓: this layer or 1; ░: not this layer or 0 )
	// 12  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓  ░░  ▓░  ░▓  ▓▓
	// 48  ░░  ░░  ░░  ░░  ░▓  ░▓  ░▓  ░▓  ▓░  ▓░  ▓░  ▓░  ▓▓  ▓▓  ▓▓  ▓▓
	//     0   1   2   3   4   5   6   7   8   9   10  11  12  13  14  15
	this.layeringstep = function(ii,cnum){
		// Creating layers for each indexed color in arr
		var layer = [], val=0, ah = ii.array.length, aw = ii.array[0].length, n1,n2,n3,n4,n5,n6,n7,n8, i, j, k;
		
		// Create layer
		for(j=0; j<ah; j++){
			layer[j] = [];
			for(i=0; i<aw; i++){
				layer[j][i]=0;
			}
		}
		
		// Looping through all pixels and calculating edge node type
		for(j=1; j<ah; j++){
			for(i=1; i<aw; i++){
				layer[j][i] =
					( ii.array[j-1][i-1]===cnum ? 1 : 0 ) +
					( ii.array[j-1][i]===cnum ? 2 : 0 ) +
					( ii.array[j][i-1]===cnum ? 8 : 0 ) +
					( ii.array[j][i]===cnum ? 4 : 0 )
				;
			}// End of i loop
		}// End of j loop
			
		return layer;
	},// End of layeringstep()
	
	// Point in polygon test
	this.pointinpoly = function( p, pa ){
		var isin=false;

		for(var i=0,j=pa.length-1; i<pa.length; j=i++){
			isin =
				( ((pa[i].y > p.y) !== (pa[j].y > p.y)) && (p.x < (pa[j].x - pa[i].x) * (p.y - pa[i].y) / (pa[j].y - pa[i].y) + pa[i].x) )
				? !isin : isin;
		}

		return isin;
	},
	
	// Lookup tables for pathscan
	// pathscan_combined_lookup[ arr[py][px] ][ dir ] = [nextarrpypx, nextdir, deltapx, deltapy];
	this.pathscan_combined_lookup = [
		[[-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1]],// arr[py][px]===0 is invalid
		[[ 0, 1, 0,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 2,-1, 0]],
		[[-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 1, 0,-1], [ 0, 0, 1, 0]],
		[[ 0, 0, 1, 0], [-1,-1,-1,-1], [ 0, 2,-1, 0], [-1,-1,-1,-1]],
		
		[[-1,-1,-1,-1], [ 0, 0, 1, 0], [ 0, 3, 0, 1], [-1,-1,-1,-1]],
		[[13, 3, 0, 1], [13, 2,-1, 0], [ 7, 1, 0,-1], [ 7, 0, 1, 0]],
		[[-1,-1,-1,-1], [ 0, 1, 0,-1], [-1,-1,-1,-1], [ 0, 3, 0, 1]],
		[[ 0, 3, 0, 1], [ 0, 2,-1, 0], [-1,-1,-1,-1], [-1,-1,-1,-1]],
		
		[[ 0, 3, 0, 1], [ 0, 2,-1, 0], [-1,-1,-1,-1], [-1,-1,-1,-1]],
		[[-1,-1,-1,-1], [ 0, 1, 0,-1], [-1,-1,-1,-1], [ 0, 3, 0, 1]],
		[[11, 1, 0,-1], [14, 0, 1, 0], [14, 3, 0, 1], [11, 2,-1, 0]],
		[[-1,-1,-1,-1], [ 0, 0, 1, 0], [ 0, 3, 0, 1], [-1,-1,-1,-1]],
		
		[[ 0, 0, 1, 0], [-1,-1,-1,-1], [ 0, 2,-1, 0], [-1,-1,-1,-1]],
		[[-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 1, 0,-1], [ 0, 0, 1, 0]],
		[[ 0, 1, 0,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [ 0, 2,-1, 0]],
		[[-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1], [-1,-1,-1,-1]]// arr[py][px]===15 is invalid
	],

	// 3. Walking through an edge node array, discarding edge node types 0 and 15 and creating paths from the rest.
	// Walk directions (dir): 0 > ; 1 ^ ; 2 < ; 3 v 
	this.pathscan = function( arr, pathomit ){
		var paths=[], pacnt=0, pcnt=0, px=0, py=0, w = arr[0].length, h = arr.length,
			dir=0, pathfinished=true, holepath=false, lookuprow;
		
		for(var j=0; j<h; j++){
			for(var i=0; i<w; i++){
				if( (arr[j][i] == 4) || ( arr[j][i] == 11) ){ // Other values are not valid
					
					// Init
					px = i; py = j;
					paths[pacnt] = {};
					paths[pacnt].points = [];
					paths[pacnt].boundingbox = [px,py,px,py];
					paths[pacnt].holechildren = [];
					pathfinished = false;
					pcnt=0;
					holepath = (arr[j][i]==11);
					dir = 1;

					// Path points loop
					while(!pathfinished){
						
						// New path point
						paths[pacnt].points[pcnt] = {};
						paths[pacnt].points[pcnt].x = px-1;
						paths[pacnt].points[pcnt].y = py-1;
						paths[pacnt].points[pcnt].t = arr[py][px];
						
						// Bounding box
						if( (px-1) < paths[pacnt].boundingbox[0] ){ paths[pacnt].boundingbox[0] = px-1; }
						if( (px-1) > paths[pacnt].boundingbox[2] ){ paths[pacnt].boundingbox[2] = px-1; }
						if( (py-1) < paths[pacnt].boundingbox[1] ){ paths[pacnt].boundingbox[1] = py-1; }
						if( (py-1) > paths[pacnt].boundingbox[3] ){ paths[pacnt].boundingbox[3] = py-1; }
						
						// Next: look up the replacement, direction and coordinate changes = clear this cell, turn if required, walk forward
						lookuprow = _this.pathscan_combined_lookup[ arr[py][px] ][ dir ];
						arr[py][px] = lookuprow[0]; dir = lookuprow[1]; px += lookuprow[2]; py += lookuprow[3];

						// Close path
						if( (px-1 === paths[pacnt].points[0].x ) && ( py-1 === paths[pacnt].points[0].y ) ){
							pathfinished = true;
							
							// Discarding paths shorter than pathomit
							if( paths[pacnt].points.length < pathomit ){
								paths.pop();
							}else{
							
								paths[pacnt].isholepath = holepath ? true : false;
								
								// Finding the parent shape for this hole
								if(holepath){
									
									var parentidx = 0, parentbbox = [-1,-1,w+1,h+1];
									for(var parentcnt=0; parentcnt < pacnt; parentcnt++){
										if( (!paths[parentcnt].isholepath) &&
											_this.boundingboxincludes( paths[parentcnt].boundingbox , paths[pacnt].boundingbox ) &&
											_this.boundingboxincludes( parentbbox , paths[parentcnt].boundingbox ) &&
											_this.pointinpoly( paths[pacnt].points[0], paths[parentcnt].points )
										){
											parentidx = parentcnt;
											parentbbox = paths[parentcnt].boundingbox;
										}
									}
									
									paths[parentidx].holechildren.push( pacnt );
									
								}// End of holepath parent finding
								
								pacnt++;
							
							}
							
						}// End of Close path
						
						pcnt++;
						
					}// End of Path points loop
					
				}// End of Follow path
				
			}// End of i loop
		}// End of j loop
		
		return paths;
	},// End of pathscan()
	
	this.boundingboxincludes = function( parentbbox, childbbox ){
		return ( ( parentbbox[0] < childbbox[0] ) && ( parentbbox[1] < childbbox[1] ) && ( parentbbox[2] > childbbox[2] ) && ( parentbbox[3] > childbbox[3] ) );
	},// End of boundingboxincludes()
	
	// 3. Batch pathscan
	this.batchpathscan = function( layers, pathomit ){
		var bpaths = [];
		for(var k in layers){
			if(!layers.hasOwnProperty(k)){ continue; }
			bpaths[k] = _this.pathscan( layers[k], pathomit );
		}
		return bpaths;
	},
	
	// 4. interpollating between path points for nodes with 8 directions ( East, SouthEast, S, SW, W, NW, N, NE )
	this.internodes = function( paths, options ){
		var ins = [], palen=0, nextidx=0, nextidx2=0, previdx=0, previdx2=0, pacnt, pcnt;
		
		// paths loop
		for(pacnt=0; pacnt<paths.length; pacnt++){
			
			ins[pacnt] = {};
			ins[pacnt].points = [];
			ins[pacnt].boundingbox = paths[pacnt].boundingbox;
			ins[pacnt].holechildren = paths[pacnt].holechildren;
			ins[pacnt].isholepath = paths[pacnt].isholepath;
			palen = paths[pacnt].points.length;
			
			// pathpoints loop
			for(pcnt=0; pcnt<palen; pcnt++){
			
				// next and previous point indexes
				nextidx = (pcnt+1)%palen; nextidx2 = (pcnt+2)%palen; previdx = (pcnt-1+palen)%palen; previdx2 = (pcnt-2+palen)%palen;
				
				// right angle enhance
				if( options.rightangleenhance && _this.testrightangle( paths[pacnt], previdx2, previdx, pcnt, nextidx, nextidx2 ) ){
					
					// Fix previous direction
					if(ins[pacnt].points.length > 0){
						ins[pacnt].points[ ins[pacnt].points.length-1 ].linesegment = _this.getdirection(
								ins[pacnt].points[ ins[pacnt].points.length-1 ].x,
								ins[pacnt].points[ ins[pacnt].points.length-1 ].y,
								paths[pacnt].points[pcnt].x,
								paths[pacnt].points[pcnt].y
							);
					}
					
					// This corner point
					ins[pacnt].points.push({
						x : paths[pacnt].points[pcnt].x,
						y : paths[pacnt].points[pcnt].y,
						linesegment : _this.getdirection(
								paths[pacnt].points[pcnt].x,
								paths[pacnt].points[pcnt].y,
								(( paths[pacnt].points[pcnt].x + paths[pacnt].points[nextidx].x ) /2),
								(( paths[pacnt].points[pcnt].y + paths[pacnt].points[nextidx].y ) /2)
							)
					});
					
				}// End of right angle enhance
				
				// interpolate between two path points
				ins[pacnt].points.push({
					x : (( paths[pacnt].points[pcnt].x + paths[pacnt].points[nextidx].x ) /2),
					y : (( paths[pacnt].points[pcnt].y + paths[pacnt].points[nextidx].y ) /2),
					linesegment : _this.getdirection(
							(( paths[pacnt].points[pcnt].x + paths[pacnt].points[nextidx].x ) /2),
							(( paths[pacnt].points[pcnt].y + paths[pacnt].points[nextidx].y ) /2),
							(( paths[pacnt].points[nextidx].x + paths[pacnt].points[nextidx2].x ) /2),
							(( paths[pacnt].points[nextidx].y + paths[pacnt].points[nextidx2].y ) /2)
						)
				});
				
			}// End of pathpoints loop
						
		}// End of paths loop
		
		return ins;
	},// End of internodes()
	
	this.testrightangle = function( path, idx1, idx2, idx3, idx4, idx5 ){
		return ( (( path.points[idx3].x === path.points[idx1].x) &&
				  ( path.points[idx3].x === path.points[idx2].x) &&
				  ( path.points[idx3].y === path.points[idx4].y) &&
				  ( path.points[idx3].y === path.points[idx5].y)
				 ) ||
				 (( path.points[idx3].y === path.points[idx1].y) &&
				  ( path.points[idx3].y === path.points[idx2].y) &&
				  ( path.points[idx3].x === path.points[idx4].x) &&
				  ( path.points[idx3].x === path.points[idx5].x)
				 )
		);
	},// End of testrightangle()
	
	this.getdirection = function( x1, y1, x2, y2 ){
		var val = 8;
		if(x1 < x2){
			if     (y1 < y2){ val = 1; }// SouthEast
			else if(y1 > y2){ val = 7; }// NE
			else            { val = 0; }// E
		}else if(x1 > x2){
			if     (y1 < y2){ val = 3; }// SW
			else if(y1 > y2){ val = 5; }// NW
			else            { val = 4; }// W
		}else{
			if     (y1 < y2){ val = 2; }// S
			else if(y1 > y2){ val = 6; }// N
			else            { val = 8; }// center, this should not happen
		}
		return val;
	},// End of getdirection()
	
	// 4. Batch interpollation
	this.batchinternodes = function( bpaths, options ){
		var binternodes = [];
		for (var k in bpaths) {
			if(!bpaths.hasOwnProperty(k)){ continue; }
			binternodes[k] = _this.internodes(bpaths[k], options);
		}
		return binternodes;
	},
	
	// 5. tracepath() : recursively trying to fit straight and quadratic spline segments on the 8 direction internode path
	
	// 5.1. Find sequences of points with only 2 segment types
	// 5.2. Fit a straight line on the sequence
	// 5.3. If the straight line fails (distance error > ltres), find the point with the biggest error
	// 5.4. Fit a quadratic spline through errorpoint (project this to get controlpoint), then measure errors on every point in the sequence
	// 5.5. If the spline fails (distance error > qtres), find the point with the biggest error, set splitpoint = fitting point
	// 5.6. Split sequence and recursively apply 5.2. - 5.6. to startpoint-splitpoint and splitpoint-endpoint sequences
	
	this.tracepath = function( path, ltres, qtres ){
		var pcnt=0, segtype1, segtype2, seqend, smp = {};
		smp.segments = [];
		smp.boundingbox = path.boundingbox;
		smp.holechildren = path.holechildren;
		smp.isholepath = path.isholepath;
		
		while(pcnt < path.points.length){
			// 5.1. Find sequences of points with only 2 segment types
			segtype1 = path.points[pcnt].linesegment; segtype2 = -1; seqend=pcnt+1;
			while(
				((path.points[seqend].linesegment === segtype1) || (path.points[seqend].linesegment === segtype2) || (segtype2 === -1))
				&& (seqend < path.points.length-1) ){
				
				if((path.points[seqend].linesegment!==segtype1) && (segtype2===-1)){ segtype2 = path.points[seqend].linesegment; }
				seqend++;
				
			}
			if(seqend === path.points.length-1){ seqend = 0; }

			// 5.2. - 5.6. Split sequence and recursively apply 5.2. - 5.6. to startpoint-splitpoint and splitpoint-endpoint sequences
			smp.segments = smp.segments.concat( _this.fitseq(path, ltres, qtres, pcnt, seqend) );
			
			// forward pcnt;
			if(seqend>0){ pcnt = seqend; }else{ pcnt = path.points.length; }
			
		}// End of pcnt loop
		
		return smp;
	},// End of tracepath()
		
	// 5.2. - 5.6. recursively fitting a straight or quadratic line segment on this sequence of path nodes,
	// called from tracepath()
	this.fitseq = function( path, ltres, qtres, seqstart, seqend ){
		// return if invalid seqend
		if( (seqend>path.points.length) || (seqend<0) ){ return []; }
		// variables
		var errorpoint=seqstart, errorval=0, curvepass=true, px, py, dist2;
		var tl = (seqend-seqstart); if(tl<0){ tl += path.points.length; }
		var vx = (path.points[seqend].x-path.points[seqstart].x) / tl,
			vy = (path.points[seqend].y-path.points[seqstart].y) / tl;
		
		// 5.2. Fit a straight line on the sequence
		var pcnt = (seqstart+1) % path.points.length, pl;
		while(pcnt != seqend){
			pl = pcnt-seqstart; if(pl<0){ pl += path.points.length; }
			px = path.points[seqstart].x + vx * pl; py = path.points[seqstart].y + vy * pl;
			dist2 = (path.points[pcnt].x-px)*(path.points[pcnt].x-px) + (path.points[pcnt].y-py)*(path.points[pcnt].y-py);
			if(dist2>ltres){curvepass=false;}
			if(dist2>errorval){ errorpoint=pcnt; errorval=dist2; }
			pcnt = (pcnt+1)%path.points.length;
		}
		// return straight line if fits
		if(curvepass){ return [{ type:'L', x1:path.points[seqstart].x, y1:path.points[seqstart].y, x2:path.points[seqend].x, y2:path.points[seqend].y }]; }
		
		// 5.3. If the straight line fails (distance error>ltres), find the point with the biggest error
		var fitpoint = errorpoint; curvepass = true; errorval = 0;
		
		// 5.4. Fit a quadratic spline through this point, measure errors on every point in the sequence
		// helpers and projecting to get control point
		var t=(fitpoint-seqstart)/tl, t1=(1-t)*(1-t), t2=2*(1-t)*t, t3=t*t;
		var cpx = (t1*path.points[seqstart].x + t3*path.points[seqend].x - path.points[fitpoint].x)/-t2 ,
			cpy = (t1*path.points[seqstart].y + t3*path.points[seqend].y - path.points[fitpoint].y)/-t2 ;
		
		// Check every point
		pcnt = seqstart+1;
		while(pcnt != seqend){
			t=(pcnt-seqstart)/tl; t1=(1-t)*(1-t); t2=2*(1-t)*t; t3=t*t;
			px = t1 * path.points[seqstart].x + t2 * cpx + t3 * path.points[seqend].x;
			py = t1 * path.points[seqstart].y + t2 * cpy + t3 * path.points[seqend].y;
			
			dist2 = (path.points[pcnt].x-px)*(path.points[pcnt].x-px) + (path.points[pcnt].y-py)*(path.points[pcnt].y-py);
			
			if(dist2>qtres){curvepass=false;}
			if(dist2>errorval){ errorpoint=pcnt; errorval=dist2; }
			pcnt = (pcnt+1)%path.points.length;
		}
		// return spline if fits
		if(curvepass){ return [{ type:'Q', x1:path.points[seqstart].x, y1:path.points[seqstart].y, x2:cpx, y2:cpy, x3:path.points[seqend].x, y3:path.points[seqend].y }]; }
		// 5.5. If the spline fails (distance error>qtres), find the point with the biggest error
		var splitpoint = fitpoint; // Earlier: Math.floor((fitpoint + errorpoint)/2);
		
		// 5.6. Split sequence and recursively apply 5.2. - 5.6. to startpoint-splitpoint and splitpoint-endpoint sequences
		return _this.fitseq( path, ltres, qtres, seqstart, splitpoint ).concat(
				_this.fitseq( path, ltres, qtres, splitpoint, seqend ) );
		
	},// End of fitseq()
	
	// 5. Batch tracing paths
	this.batchtracepaths = function(internodepaths,ltres,qtres){
		var btracedpaths = [];
		for(var k in internodepaths){
			if(!internodepaths.hasOwnProperty(k)){ continue; }
			btracedpaths.push( _this.tracepath(internodepaths[k],ltres,qtres) );
		}
		return btracedpaths;
	},
	
	// 5. Batch tracing layers
	this.batchtracelayers = function(binternodes, ltres, qtres){
		var btbis = [];
		for(var k in binternodes){
			if(!binternodes.hasOwnProperty(k)){ continue; }
			btbis[k] = _this.batchtracepaths(binternodes[k], ltres, qtres);
		}
		return btbis;
	},
	
	////////////////////////////////////////////////////////////
	//
	//  SVG Drawing functions
	//
	////////////////////////////////////////////////////////////
	
	// Rounding to given decimals https://stackoverflow.com/questions/11832914/round-to-at-most-2-decimal-places-in-javascript
	this.roundtodec = function(val,places){ return +val.toFixed(places); },
	
	// Getting SVG path element string from a traced path
	this.svgpathstring = function( tracedata, lnum, pathnum, options ){
		
		var layer = tracedata.layers[lnum], smp = layer[pathnum], str='', pcnt;
		
		// Line filter
		if(options.linefilter && (smp.segments.length < 3)){ return str; }
		
		// Starting path element, desc contains layer and path number
		str = '<path '+
			( options.desc ? ('desc="l '+lnum+' p '+pathnum+'" ') : '' ) +
			_this.tosvgcolorstr(tracedata.palette[lnum], options) +
			'd="';
		
		// Creating non-hole path string
		if( options.roundcoords === -1 ){
			str += 'M '+ smp.segments[0].x1 * options.scale +' '+ smp.segments[0].y1 * options.scale +' ';
			for(pcnt=0; pcnt<smp.segments.length; pcnt++){
				str += smp.segments[pcnt].type +' '+ smp.segments[pcnt].x2 * options.scale +' '+ smp.segments[pcnt].y2 * options.scale +' ';
				if(smp.segments[pcnt].hasOwnProperty('x3')){
					str += smp.segments[pcnt].x3 * options.scale +' '+ smp.segments[pcnt].y3 * options.scale +' ';
				}
			}
			str += 'Z ';
		}else{
			str += 'M '+ _this.roundtodec( smp.segments[0].x1 * options.scale, options.roundcoords ) +' '+ _this.roundtodec( smp.segments[0].y1 * options.scale, options.roundcoords ) +' ';
			for(pcnt=0; pcnt<smp.segments.length; pcnt++){
				str += smp.segments[pcnt].type +' '+ _this.roundtodec( smp.segments[pcnt].x2 * options.scale, options.roundcoords ) +' '+ _this.roundtodec( smp.segments[pcnt].y2 * options.scale, options.roundcoords ) +' ';
				if(smp.segments[pcnt].hasOwnProperty('x3')){
					str += _this.roundtodec( smp.segments[pcnt].x3 * options.scale, options.roundcoords ) +' '+ _this.roundtodec( smp.segments[pcnt].y3 * options.scale, options.roundcoords ) +' ';
				}
			}
			str += 'Z ';
		}// End of creating non-hole path string
		
		// Hole children
		for( var hcnt=0; hcnt < smp.holechildren.length; hcnt++){
			var hsmp = layer[ smp.holechildren[hcnt] ];
			// Creating hole path string
			if( options.roundcoords === -1 ){
				
				if(hsmp.segments[ hsmp.segments.length-1 ].hasOwnProperty('x3')){
					str += 'M '+ hsmp.segments[ hsmp.segments.length-1 ].x3 * options.scale +' '+ hsmp.segments[ hsmp.segments.length-1 ].y3 * options.scale +' ';
				}else{
					str += 'M '+ hsmp.segments[ hsmp.segments.length-1 ].x2 * options.scale +' '+ hsmp.segments[ hsmp.segments.length-1 ].y2 * options.scale +' ';
				}
				
				for(pcnt = hsmp.segments.length-1; pcnt >= 0; pcnt--){
					str += hsmp.segments[pcnt].type +' ';
					if(hsmp.segments[pcnt].hasOwnProperty('x3')){
						str += hsmp.segments[pcnt].x2 * options.scale +' '+ hsmp.segments[pcnt].y2 * options.scale +' ';
					}
					
					str += hsmp.segments[pcnt].x1 * options.scale +' '+ hsmp.segments[pcnt].y1 * options.scale +' ';
				}
				
			}else{
				
				if(hsmp.segments[ hsmp.segments.length-1 ].hasOwnProperty('x3')){
					str += 'M '+ _this.roundtodec( hsmp.segments[ hsmp.segments.length-1 ].x3 * options.scale ) +' '+ _this.roundtodec( hsmp.segments[ hsmp.segments.length-1 ].y3 * options.scale ) +' ';
				}else{
					str += 'M '+ _this.roundtodec( hsmp.segments[ hsmp.segments.length-1 ].x2 * options.scale ) +' '+ _this.roundtodec( hsmp.segments[ hsmp.segments.length-1 ].y2 * options.scale ) +' ';
				}
				
				for(pcnt = hsmp.segments.length-1; pcnt >= 0; pcnt--){
					str += hsmp.segments[pcnt].type +' ';
					if(hsmp.segments[pcnt].hasOwnProperty('x3')){
						str += _this.roundtodec( hsmp.segments[pcnt].x2 * options.scale ) +' '+ _this.roundtodec( hsmp.segments[pcnt].y2 * options.scale ) +' ';
					}
					str += _this.roundtodec( hsmp.segments[pcnt].x1 * options.scale ) +' '+ _this.roundtodec( hsmp.segments[pcnt].y1 * options.scale ) +' ';
				}
				
				
			}// End of creating hole path string
			
			str += 'Z '; // Close path
			
		}// End of holepath check
		
		// Closing path element
		str += '" />';
		
		// Rendering control points
		if(options.lcpr || options.qcpr){
			for(pcnt=0; pcnt<smp.segments.length; pcnt++){
				if( smp.segments[pcnt].hasOwnProperty('x3') && options.qcpr ){
					str += '<circle cx="'+ smp.segments[pcnt].x2 * options.scale +'" cy="'+ smp.segments[pcnt].y2 * options.scale +'" r="'+ options.qcpr +'" fill="cyan" stroke-width="'+ options.qcpr * 0.2 +'" stroke="black" />';
					str += '<circle cx="'+ smp.segments[pcnt].x3 * options.scale +'" cy="'+ smp.segments[pcnt].y3 * options.scale +'" r="'+ options.qcpr +'" fill="white" stroke-width="'+ options.qcpr * 0.2 +'" stroke="black" />';
					str += '<line x1="'+ smp.segments[pcnt].x1 * options.scale +'" y1="'+ smp.segments[pcnt].y1 * options.scale +'" x2="'+ smp.segments[pcnt].x2 * options.scale +'" y2="'+ smp.segments[pcnt].y2 * options.scale +'" stroke-width="'+ options.qcpr * 0.2 +'" stroke="cyan" />';
					str += '<line x1="'+ smp.segments[pcnt].x2 * options.scale +'" y1="'+ smp.segments[pcnt].y2 * options.scale +'" x2="'+ smp.segments[pcnt].x3 * options.scale +'" y2="'+ smp.segments[pcnt].y3 * options.scale +'" stroke-width="'+ options.qcpr * 0.2 +'" stroke="cyan" />';
				}
				if( (!smp.segments[pcnt].hasOwnProperty('x3')) && options.lcpr){
					str += '<circle cx="'+ smp.segments[pcnt].x2 * options.scale +'" cy="'+ smp.segments[pcnt].y2 * options.scale +'" r="'+ options.lcpr +'" fill="white" stroke-width="'+ options.lcpr * 0.2 +'" stroke="black" />';
				}
			}
			
			// Hole children control points
			for( var hcnt=0; hcnt < smp.holechildren.length; hcnt++){
				var hsmp = layer[ smp.holechildren[hcnt] ];
				for(pcnt=0; pcnt<hsmp.segments.length; pcnt++){
					if( hsmp.segments[pcnt].hasOwnProperty('x3') && options.qcpr ){
						str += '<circle cx="'+ hsmp.segments[pcnt].x2 * options.scale +'" cy="'+ hsmp.segments[pcnt].y2 * options.scale +'" r="'+ options.qcpr +'" fill="cyan" stroke-width="'+ options.qcpr * 0.2 +'" stroke="black" />';
						str += '<circle cx="'+ hsmp.segments[pcnt].x3 * options.scale +'" cy="'+ hsmp.segments[pcnt].y3 * options.scale +'" r="'+ options.qcpr +'" fill="white" stroke-width="'+ options.qcpr * 0.2 +'" stroke="black" />';
						str += '<line x1="'+ hsmp.segments[pcnt].x1 * options.scale +'" y1="'+ hsmp.segments[pcnt].y1 * options.scale +'" x2="'+ hsmp.segments[pcnt].x2 * options.scale +'" y2="'+ hsmp.segments[pcnt].y2 * options.scale +'" stroke-width="'+ options.qcpr * 0.2 +'" stroke="cyan" />';
						str += '<line x1="'+ hsmp.segments[pcnt].x2 * options.scale +'" y1="'+ hsmp.segments[pcnt].y2 * options.scale +'" x2="'+ hsmp.segments[pcnt].x3 * options.scale +'" y2="'+ hsmp.segments[pcnt].y3 * options.scale +'" stroke-width="'+ options.qcpr * 0.2 +'" stroke="cyan" />';
					}
					if( (!hsmp.segments[pcnt].hasOwnProperty('x3')) && options.lcpr){
						str += '<circle cx="'+ hsmp.segments[pcnt].x2 * options.scale +'" cy="'+ hsmp.segments[pcnt].y2 * options.scale +'" r="'+ options.lcpr +'" fill="white" stroke-width="'+ options.lcpr * 0.2 +'" stroke="black" />';
					}
				}
			}
		}// End of Rendering control points
			
		return str;
		
	},// End of svgpathstring()
	
	// Converting tracedata to an SVG string
	this.getsvgstring = function( tracedata, options ){
		
		options = _this.checkoptions(options);
		
		var w = tracedata.width * options.scale, h = tracedata.height * options.scale;
		
		// SVG start
		var svgstr = '<svg ' + (options.viewbox ? ('viewBox="0 0 '+w+' '+h+'" ') : ('width="'+w+'" height="'+h+'" ')) +
			'version="1.1" xmlns="http://www.w3.org/2000/svg" desc="Created with imagetracer.js version '+_this.versionnumber+'" >';

		// Drawing: Layers and Paths loops
		for(var lcnt=0; lcnt < tracedata.layers.length; lcnt++){
			for(var pcnt=0; pcnt < tracedata.layers[lcnt].length; pcnt++){
				
				// Adding SVG <path> string
				if( !tracedata.layers[lcnt][pcnt].isholepath ){
					svgstr += _this.svgpathstring( tracedata, lcnt, pcnt, options );
				}
					
			}// End of paths loop
		}// End of layers loop
		
		// SVG End
		svgstr+='</svg>';
		
		return svgstr;
		
	},// End of getsvgstring()
	
	// Comparator for numeric Array.sort
	this.compareNumbers = function(a,b){ return a - b; },
	
	// Convert color object to rgba string
	this.torgbastr = function(c){ return 'rgba('+c.r+','+c.g+','+c.b+','+c.a+')'; },
	
	// Convert color object to SVG color string
	this.tosvgcolorstr = function(c, options){
		return 'fill="rgb('+c.r+','+c.g+','+c.b+')" stroke="rgb('+c.r+','+c.g+','+c.b+')" stroke-width="'+options.strokewidth+'" opacity="'+c.a/255.0+'" ';
	},
	
	// Helper function: Appending an <svg> element to a container from an svgstring
	this.appendSVGString = function(svgstr,parentid){
		var div;
		if(parentid){
			div = document.getElementById(parentid);
			if(!div){
				div = document.createElement('div');
				div.id = parentid;
				document.body.appendChild(div);
			}
		}else{
			div = document.createElement('div');
			document.body.appendChild(div);
		}
		div.innerHTML += svgstr;
	},
	
	////////////////////////////////////////////////////////////
	//
	//  Canvas functions
	//
	////////////////////////////////////////////////////////////
	
	// Gaussian kernels for blur
	this.gks = [ [0.27901,0.44198,0.27901], [0.135336,0.228569,0.272192,0.228569,0.135336], [0.086776,0.136394,0.178908,0.195843,0.178908,0.136394,0.086776],
	             [0.063327,0.093095,0.122589,0.144599,0.152781,0.144599,0.122589,0.093095,0.063327], [0.049692,0.069304,0.089767,0.107988,0.120651,0.125194,0.120651,0.107988,0.089767,0.069304,0.049692] ],
	
	// Selective Gaussian blur for preprocessing
	this.blur = function(imgd,radius,delta){
		var i,j,k,d,idx,racc,gacc,bacc,aacc,wacc;
		
		// new ImageData
		var imgd2 = { width:imgd.width, height:imgd.height, data:[] };
		
		// radius and delta limits, this kernel
		radius = Math.floor(radius); if(radius<1){ return imgd; } if(radius>5){ radius = 5; } delta = Math.abs( delta ); if(delta>1024){ delta = 1024; }
		var thisgk = _this.gks[radius-1];
		
		// loop through all pixels, horizontal blur
		for( j=0; j < imgd.height; j++ ){
			for( i=0; i < imgd.width; i++ ){

				racc = 0; gacc = 0; bacc = 0; aacc = 0; wacc = 0;
				// gauss kernel loop
				for( k = -radius; k < radius+1; k++){
					// add weighted color values
					if( (i+k > 0) && (i+k < imgd.width) ){
						idx = (j*imgd.width+i+k)*4;
						racc += imgd.data[idx  ] * thisgk[k+radius];
						gacc += imgd.data[idx+1] * thisgk[k+radius];
						bacc += imgd.data[idx+2] * thisgk[k+radius];
						aacc += imgd.data[idx+3] * thisgk[k+radius];
						wacc += thisgk[k+radius];
					}
				}
				// The new pixel
				idx = (j*imgd.width+i)*4;
				imgd2.data[idx  ] = Math.floor(racc / wacc);
				imgd2.data[idx+1] = Math.floor(gacc / wacc);
				imgd2.data[idx+2] = Math.floor(bacc / wacc);
				imgd2.data[idx+3] = Math.floor(aacc / wacc);
				
			}// End of width loop
		}// End of horizontal blur
		
		// copying the half blurred imgd2
		var himgd = new Uint8ClampedArray(imgd2.data);
		
		// loop through all pixels, vertical blur
		for( j=0; j < imgd.height; j++ ){
			for( i=0; i < imgd.width; i++ ){

				racc = 0; gacc = 0; bacc = 0; aacc = 0; wacc = 0;
				// gauss kernel loop
				for( k = -radius; k < radius+1; k++){
					// add weighted color values
					if( (j+k > 0) && (j+k < imgd.height) ){
						idx = ((j+k)*imgd.width+i)*4;
						racc += himgd[idx  ] * thisgk[k+radius];
						gacc += himgd[idx+1] * thisgk[k+radius];
						bacc += himgd[idx+2] * thisgk[k+radius];
						aacc += himgd[idx+3] * thisgk[k+radius];
						wacc += thisgk[k+radius];
					}
				}
				// The new pixel
				idx = (j*imgd.width+i)*4;
				imgd2.data[idx  ] = Math.floor(racc / wacc);
				imgd2.data[idx+1] = Math.floor(gacc / wacc);
				imgd2.data[idx+2] = Math.floor(bacc / wacc);
				imgd2.data[idx+3] = Math.floor(aacc / wacc);
				
			}// End of width loop
		}// End of vertical blur
		
		// Selective blur: loop through all pixels
		for( j=0; j < imgd.height; j++ ){
			for( i=0; i < imgd.width; i++ ){
				
				idx = (j*imgd.width+i)*4;
				// d is the difference between the blurred and the original pixel
				d = Math.abs(imgd2.data[idx  ] - imgd.data[idx  ]) + Math.abs(imgd2.data[idx+1] - imgd.data[idx+1]) +
					Math.abs(imgd2.data[idx+2] - imgd.data[idx+2]) + Math.abs(imgd2.data[idx+3] - imgd.data[idx+3]);
				// selective blur: if d>delta, put the original pixel back
				if(d>delta){
					imgd2.data[idx  ] = imgd.data[idx  ];
					imgd2.data[idx+1] = imgd.data[idx+1];
					imgd2.data[idx+2] = imgd.data[idx+2];
					imgd2.data[idx+3] = imgd.data[idx+3];
				}
			}
		}// End of Selective blur
		
		return imgd2;
		
	},// End of blur()
	
	// Helper function: loading an image from a URL, then executing callback with canvas as argument
	this.loadImage = function(url,callback,options){
		var img = new Image();
		if(options && options.corsenabled){ img.crossOrigin = 'Anonymous'; }
		img.onload = function(){
			var canvas = document.createElement('canvas');
			canvas.width = img.width;
			canvas.height = img.height;
			var context = canvas.getContext('2d');
			context.drawImage(img,0,0);
			callback(canvas);
		};
		img.src = url;
	},
	
	// Helper function: getting ImageData from a canvas
	this.getImgdata = function(canvas){
		var context = canvas.getContext('2d');
		return context.getImageData(0,0,canvas.width,canvas.height);
	},
	
	// Special palette to use with drawlayers()
	this.specpalette = [
		{r:0,g:0,b:0,a:255}, {r:128,g:128,b:128,a:255}, {r:0,g:0,b:128,a:255}, {r:64,g:64,b:128,a:255},
		{r:192,g:192,b:192,a:255}, {r:255,g:255,b:255,a:255}, {r:128,g:128,b:192,a:255}, {r:0,g:0,b:192,a:255},
		{r:128,g:0,b:0,a:255}, {r:128,g:64,b:64,a:255}, {r:128,g:0,b:128,a:255}, {r:168,g:168,b:168,a:255},
		{r:192,g:128,b:128,a:255}, {r:192,g:0,b:0,a:255}, {r:255,g:255,b:255,a:255}, {r:0,g:128,b:0,a:255}
	],
	
	// Helper function: Drawing all edge node layers into a container
	this.drawLayers = function(layers,palette,scale,parentid){
		scale = scale||1;
		var w,h,i,j,k;
		
		// Preparing container
		var div;
		if(parentid){
			div = document.getElementById(parentid);
			if(!div){
				div = document.createElement('div');
				div.id = parentid;
				document.body.appendChild(div);
			}
		}else{
			div = document.createElement('div');
			document.body.appendChild(div);
		}
		
		// Layers loop
		for (k in layers) {
			if(!layers.hasOwnProperty(k)){ continue; }
			
			// width, height
			w=layers[k][0].length; h=layers[k].length;
			
			// Creating new canvas for every layer
			var canvas = document.createElement('canvas'); canvas.width=w*scale; canvas.height=h*scale;
			var context = canvas.getContext('2d');
			
			// Drawing
			for(j=0; j<h; j++){
				for(i=0; i<w; i++){
					context.fillStyle = _this.torgbastr(palette[ layers[k][j][i]%palette.length ]);
					context.fillRect(i*scale,j*scale,scale,scale);
				}
			}
			
			// Appending canvas to container
			div.appendChild(canvas);
		}// End of Layers loop
	}// End of drawlayers
	
	;// End of function list
	
}// End of ImageTracer object

// export as AMD module / Node module / browser or worker variable
if(true){
	!(__WEBPACK_AMD_DEFINE_RESULT__ = (function() { return new ImageTracer(); }).call(exports, __webpack_require__, exports, module),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
}else // removed by dead control flow
{}

})();

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
/*!**************************************!*\
  !*** ./src/admin/clipart-manager.js ***!
  \**************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var imagetracerjs__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! imagetracerjs */ "./node_modules/imagetracerjs/imagetracer_v1.2.6.js");
/* harmony import */ var imagetracerjs__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(imagetracerjs__WEBPACK_IMPORTED_MODULE_0__);


/* eslint-disable no-console, no-alert, no-undef, @wordpress/no-unused-vars-before-return, no-unused-vars */

/**
 * Clipart Manager admin JS.
 *
 * Handles:
 *  - Tab switching (Clipart / Clipart Groups)
 *  - Upload modal with drag-and-drop (step 1 then step 2 then AJAX)
 *  - Edit modal — rename via AJAX; delete via server redirect link
 *  - Clipart group editor modal (create / update / delete)
 */

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const TRACE_MAX_SIZE = 1200;
const PRINT_METHODS = ['engraving', 'uv', 'embroidery', 'sublimation'];
const PRINT_METHOD_LABELS = {
  engraving: 'Engraving',
  uv: 'UV Printing',
  embroidery: 'Embroidery',
  sublimation: 'Sublimation'
};
const clipart = (window.ocClipartData || []).map(normaliseClipart);
let groups = (window.ocClipartGroups || []).map(normaliseGroup);
let currentFile = null;
let editClipartId = null;
let editGroupId = null;
let uploadModalGeneration = 0;
let editModalGeneration = 0;
let groupModalGeneration = 0;
let uploadWrite = null;
let editWrite = null;
let groupWrite = null;

// ---------------------------------------------------------------------------
// Normalisers
// ---------------------------------------------------------------------------

function normaliseClipart(c) {
  return {
    id: Number(c.id),
    name: c.name || '',
    fileType: c.fileType || '',
    canConvert: !!c.canConvert,
    colourChangeable: c.colourChangeable !== false,
    allowedPrintMethods: normalisePrintMethods(c.allowedPrintMethods || []),
    active: !!c.active,
    url: c.url || '',
    toggleUrl: c.toggleUrl || '',
    deleteUrl: c.deleteUrl || ''
  };
}
function normalisePrintMethods(methods) {
  return (Array.isArray(methods) ? methods : []).filter(method => PRINT_METHODS.includes(method));
}
function methodSummary(methods) {
  methods = normalisePrintMethods(methods);
  if (!methods.length) {
    return 'All print methods';
  }
  return methods.map(method => PRINT_METHOD_LABELS[method] || method).join(', ');
}
function checkedMethods(selector) {
  return [...document.querySelectorAll(`${selector}:checked`)].map(input => input.value);
}
function setCheckedMethods(selector, methods) {
  methods = normalisePrintMethods(methods);
  document.querySelectorAll(selector).forEach(input => {
    input.checked = methods.includes(input.value);
  });
}
function normaliseGroup(g) {
  return {
    id: Number(g.id),
    name: g.name || '',
    clipartIds: (g.clipartIds || []).map(Number)
  };
}

// ---------------------------------------------------------------------------
// Escape helper
// ---------------------------------------------------------------------------

function h(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ---------------------------------------------------------------------------
// Tab switching
// ---------------------------------------------------------------------------

function initTabs() {
  const tabs = document.querySelectorAll('.oc-tab');
  const panels = document.querySelectorAll('.oc-tab-panel');
  const uploadBtn = document.getElementById('oc-upload-clipart-btn');
  const createGrpBtn = document.getElementById('oc-create-clipart-group-btn');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('oc-tab--active'));
      panels.forEach(p => {
        p.hidden = true;
      });
      tab.classList.add('oc-tab--active');
      const target = document.getElementById(tab.dataset.target);
      if (target) {
        target.hidden = false;
      }
      const isGroups = tab.dataset.target === 'oc-tab-clipart-groups';
      if (uploadBtn) {
        uploadBtn.style.display = isGroups ? 'none' : '';
      }
      if (createGrpBtn) {
        createGrpBtn.style.display = isGroups ? 'inline-flex' : 'none';
      }
    });
  });
}

// ---------------------------------------------------------------------------
// Clipart card grid
// ---------------------------------------------------------------------------

function buildClipartCardEl(item) {
  const card = document.createElement('div');
  card.className = 'oc-clipart-card' + (item.active ? '' : ' oc-clipart-card--inactive');
  card.dataset.clipartId = item.id;
  card.setAttribute('role', 'button');
  card.setAttribute('tabindex', '0');
  card.innerHTML = '<div class="oc-clipart-preview">' + '<img src="' + h(item.url) + '" alt="' + h(item.name) + '" loading="lazy" />' + '</div>' + '<div class="oc-clipart-card-body">' + '<div class="oc-clipart-card-title-row">' + '<p class="oc-clipart-card-name" title="' + h(item.name) + '">' + h(item.name) + '</p>' + '<span class="oc-badge ' + (item.active ? 'oc-badge-active' : 'oc-badge-inactive') + '">' + (item.active ? 'Active' : 'Inactive') + '</span>' + '</div>' + '<p class="oc-clipart-type-label">' + h(item.fileType.toUpperCase()) + ' · ' + (item.colourChangeable ? 'Colour changeable' : 'Fixed colour') + '</p>' + '<p class="oc-clipart-type-label">' + h(methodSummary(item.allowedPrintMethods)) + '</p>' + '<div class="oc-clipart-card-actions">' + (item.canConvert ? '<button type="button" class="oc-btn oc-btn-secondary oc-btn-sm" data-oc-convert-clipart="' + item.id + '">Convert to SVG</button>' : '') + '<a href="' + h(item.toggleUrl) + '" class="oc-btn oc-btn-secondary oc-btn-sm">' + (item.active ? 'Deactivate' : 'Activate') + '</a>' + '<a href="' + h(item.deleteUrl) + '" onclick="return confirm(\'Delete this clipart?\');" class="oc-btn oc-btn-danger oc-btn-sm">Delete</a>' + '</div>' + '</div>';
  bindClipartCard(card);
  return card;
}
function isCardActionEvent(event) {
  const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
  if (path.some(el => el instanceof Element && el.closest?.('a,button,[data-oc-convert-clipart]'))) {
    return true;
  }
  return event.target instanceof Element && !!event.target.closest('a,button,[data-oc-convert-clipart]');
}
async function convertClipartToSvg(id, button) {
  const item = clipart.find(c => c.id === Number(id));
  if (!item) {
    return;
  }
  const originalText = button?.textContent || 'Convert to SVG';
  if (button) {
    button.disabled = true;
    button.textContent = 'Converting...';
  }
  const body = new URLSearchParams({
    action: 'oc_clipart_convert_svg',
    nonce: window.ocClipartNonce,
    id: Number(id)
  });
  try {
    let requestBody = body;
    try {
      const svg = await traceUrlToSvg(item.url);
      const fd = new FormData();
      fd.append('action', 'oc_clipart_convert_svg');
      fd.append('nonce', window.ocClipartNonce);
      fd.append('id', Number(id));
      fd.append('clipart_file', new Blob([svg], {
        type: 'image/svg+xml'
      }), `${safeFilename(item.name) || 'clipart'}.svg`);
      requestBody = fd;
    } catch (traceErr) {
      console.warn('[OC] Browser clipart tracing failed; using server fallback:', traceErr);
    }
    const res = await fetch(window.ocAjaxUrl, {
      method: 'POST',
      body: requestBody
    });
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const json = await res.json();
    if (!json.success) {
      throw new Error(json.data?.message || 'Conversion failed.');
    }
    const converted = normaliseClipart(json.data);
    const idx = clipart.findIndex(c => c.id === converted.id);
    if (idx !== -1) {
      clipart[idx] = converted;
      updateClipartGridUI();
    }
  } catch (err) {
    alert(err?.message || 'Conversion failed. Please try again.');
    if (button) {
      button.disabled = false;
      button.textContent = originalText;
    }
  }
}
async function traceUrlToSvg(url) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    cache: 'no-store'
  });
  if (!response.ok) {
    throw new Error(`Could not load clipart (${response.status}).`);
  }
  return traceBlobToSvg(await response.blob());
}
async function traceBlobToSvg(blob) {
  const image = await loadImageFromBlob(blob);
  const scale = Math.min(1, TRACE_MAX_SIZE / Math.max(image.naturalWidth || image.width, image.naturalHeight || image.height));
  const width = Math.max(1, Math.round((image.naturalWidth || image.width) * scale));
  const height = Math.max(1, Math.round((image.naturalHeight || image.height) * scale));
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d', {
    willReadFrequently: true
  });
  if (!ctx) {
    throw new Error('Canvas is unavailable for tracing.');
  }
  ctx.clearRect(0, 0, width, height);
  ctx.drawImage(image, 0, 0, width, height);
  const imgData = ctx.getImageData(0, 0, width, height);
  const data = imgData.data;
  for (let i = 0; i < data.length; i += 4) {
    const r = data[i];
    const g = data[i + 1];
    const b = data[i + 2];
    const a = data[i + 3];
    const nearWhite = r >= 245 && g >= 245 && b >= 245 && Math.max(r, g, b) - Math.min(r, g, b) <= 12;
    if (a < 16 || nearWhite) {
      data[i] = 255;
      data[i + 1] = 255;
      data[i + 2] = 255;
      data[i + 3] = 0;
    } else {
      data[i] = 0;
      data[i + 1] = 0;
      data[i + 2] = 0;
      data[i + 3] = 255;
    }
  }
  let svg = imagetracerjs__WEBPACK_IMPORTED_MODULE_0___default().imagedataToSVG(imgData, {
    colorsampling: 0,
    pal: [{
      r: 0,
      g: 0,
      b: 0,
      a: 255
    }, {
      r: 255,
      g: 255,
      b: 255,
      a: 0
    }],
    numberofcolors: 2,
    pathomit: 4,
    ltres: 1,
    qtres: 1,
    strokewidth: 0,
    roundcoords: 1,
    viewbox: true,
    desc: false
  });
  svg = svg.replace(/<path[^>]+fill="rgba\(255,255,255,0\)"[^>]*>\s*<\/path>/g, '').replace(/fill="rgb\(0,0,0\)"/g, 'fill="currentColor"').replace(/stroke="rgb\(0,0,0\)"/g, 'stroke="currentColor"').replace(/<svg\b/, '<svg color="#000000" data-oc-traced="browser"');
  if (!/<path\b/.test(svg)) {
    throw new Error('No traceable artwork found after background removal.');
  }
  return svg;
}
function loadImageFromBlob(blob) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob);
    const image = new Image();
    image.onload = () => {
      URL.revokeObjectURL(url);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Could not load image for tracing.'));
    };
    image.src = url;
  });
}
function safeFilename(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
}
function updateClipartGridUI() {
  const grid = document.getElementById('oc-clipart-grid');
  const empty = document.getElementById('oc-clipart-empty');
  const count = document.getElementById('oc-clipart-count');
  const tab = document.querySelector('.oc-tab[data-target="oc-tab-clipart"] .oc-tab-count');
  if (!grid) {
    return;
  }
  if (count) {
    count.textContent = clipart.length + ' ' + (1 === clipart.length ? 'item' : 'items');
  }
  if (tab) {
    tab.textContent = clipart.length;
  }
  if (clipart.length === 0) {
    if (empty) {
      empty.style.display = '';
    }
    grid.style.display = 'none';
    return;
  }
  if (empty) {
    empty.style.display = 'none';
  }
  grid.style.display = '';
  grid.innerHTML = '';
  clipart.forEach(c => {
    grid.appendChild(buildClipartCardEl(c));
  });
  document.getElementById('oc-clipart-load-more')?.parentElement?.remove();
}
function bindClipartCard(card) {
  if (card.dataset.ocHandlersBound === 'true') {
    return;
  }
  card.dataset.ocHandlersBound = 'true';
  card.addEventListener('click', e => {
    if (isCardActionEvent(e)) {
      return;
    }
    openEditModal(Number(card.dataset.clipartId));
  });
  card.querySelector('[data-oc-convert-clipart]')?.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    convertClipartToSvg(Number(card.dataset.clipartId), e.currentTarget);
  });
  card.addEventListener('keydown', e => {
    if (isCardActionEvent(e)) {
      return;
    }
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openEditModal(Number(card.dataset.clipartId));
    }
  });
}

// ---------------------------------------------------------------------------
// Upload modal
// ---------------------------------------------------------------------------

function initUploadModal() {
  const modal = document.getElementById('oc-upload-clipart-modal');
  const openBtn = document.getElementById('oc-upload-clipart-btn');
  const closeBtn = document.getElementById('oc-clipart-upload-modal-close');
  const dropZone = document.getElementById('oc-clipart-drop-zone');
  const fileInput = document.getElementById('oc_clipart_file');
  const step1 = document.getElementById('oc-clipart-upload-step-1');
  const step2 = document.getElementById('oc-clipart-upload-step-2');
  const footer = document.getElementById('oc-clipart-upload-modal-footer');
  const previewImg = document.getElementById('oc-clipart-upload-preview-img');
  const nameInput = document.getElementById('oc_clipart_upload_name');
  const errDiv = document.getElementById('oc-clipart-upload-error');
  const backBtn = document.getElementById('oc-clipart-upload-back-btn');
  const submitBtn = document.getElementById('oc-clipart-upload-submit-btn');
  if (!modal) {
    return;
  }
  function isCurrentUploadContext(request) {
    return uploadWrite === request && request.generation === uploadModalGeneration && currentFile === request.file && !modal.hidden;
  }
  function syncUploadWriteControls() {
    if (!submitBtn) {
      return;
    }
    const busy = !!uploadWrite;
    submitBtn.dataset.label ||= submitBtn.textContent;
    submitBtn.disabled = busy;
    submitBtn.setAttribute('aria-disabled', busy ? 'true' : 'false');
    submitBtn.textContent = busy ? 'Uploading...' : submitBtn.dataset.label;
  }
  function openModal() {
    resetToStep1();
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    syncUploadWriteControls();
  }
  function closeModal() {
    uploadModalGeneration++;
    uploadWrite = null;
    modal.hidden = true;
    document.body.style.overflow = '';
    currentFile = null;
    syncUploadWriteControls();
  }
  function resetToStep1() {
    uploadModalGeneration++;
    uploadWrite = null;
    if (step1) {
      step1.style.display = '';
    }
    if (step2) {
      step2.style.display = 'none';
    }
    if (footer) {
      footer.style.display = 'none';
    }
    if (errDiv) {
      errDiv.style.display = 'none';
      errDiv.textContent = '';
    }
    const colourChangeable = document.getElementById('oc_clipart_upload_colour_changeable');
    if (colourChangeable) {
      colourChangeable.checked = true;
    }
    setCheckedMethods('.oc-clipart-upload-method-check', []);
    currentFile = null;
    syncUploadWriteControls();
  }
  function showStep2(file) {
    uploadModalGeneration++;
    uploadWrite = null;
    currentFile = file;
    if (previewImg) {
      const url = URL.createObjectURL(file);
      previewImg.src = url;
      previewImg.onload = () => URL.revokeObjectURL(url);
    }
    if (nameInput && !nameInput.value) {
      nameInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    if (step1) {
      step1.style.display = 'none';
    }
    if (step2) {
      step2.style.display = '';
    }
    if (footer) {
      footer.style.display = '';
    }
    if (errDiv) {
      errDiv.style.display = 'none';
    }
    syncUploadWriteControls();
  }
  function handleFile(file) {
    if (!/\.(svg|png|jpe?g|webp|gif)$/i.test(file.name)) {
      alert('File type not supported. Use SVG, PNG, JPG, WEBP, or GIF.');
      return;
    }
    showStep2(file);
  }
  dropZone?.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('oc-drop-zone--over');
  });
  dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('oc-drop-zone--over'));
  dropZone?.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('oc-drop-zone--over');
    const file = e.dataTransfer.files[0];
    if (file) {
      handleFile(file);
    }
  });
  dropZone?.addEventListener('click', () => fileInput?.click());
  fileInput?.addEventListener('change', () => {
    if (fileInput.files[0]) {
      handleFile(fileInput.files[0]);
    }
  });
  openBtn?.addEventListener('click', openModal);
  closeBtn?.addEventListener('click', closeModal);
  backBtn?.addEventListener('click', () => {
    if (nameInput) {
      nameInput.value = '';
    }
    resetToStep1();
  });
  modal?.addEventListener('click', e => {
    if (e.target === modal) {
      closeModal();
    }
  });
  submitBtn?.addEventListener('click', async () => {
    if (!currentFile || uploadWrite) {
      return;
    }
    const file = currentFile;
    const name = nameInput?.value.trim() || '';
    if (!name) {
      if (errDiv) {
        errDiv.textContent = 'Name is required.';
        errDiv.style.display = '';
      }
      return;
    }
    const colourChangeable = document.getElementById('oc_clipart_upload_colour_changeable')?.checked;
    const methods = checkedMethods('.oc-clipart-upload-method-check');
    const request = {
      generation: uploadModalGeneration,
      file,
      mode: 'upload'
    };
    uploadWrite = request;
    syncUploadWriteControls();
    try {
      let uploadFile = file;
      if (!/\.svg$/i.test(file.name)) {
        try {
          const svg = await traceBlobToSvg(file);
          if (!isCurrentUploadContext(request)) {
            return;
          }
          uploadFile = new File([svg], `${safeFilename(name) || 'clipart'}.svg`, {
            type: 'image/svg+xml'
          });
        } catch (traceErr) {
          if (isCurrentUploadContext(request)) {
            console.warn('[OC] Browser clipart tracing failed; uploading for server fallback:', traceErr);
          } else {
            return;
          }
        }
      }
      const fd = new FormData();
      fd.append('action', 'oc_clipart_upload');
      fd.append('nonce', window.ocClipartNonce);
      fd.append('name', name);
      fd.append('colour_changeable', colourChangeable ? '1' : '0');
      methods.forEach(method => fd.append('allowed_print_methods[]', method));
      fd.append('clipart_file', uploadFile);
      const res = await fetch(window.ocAjaxUrl, {
        method: 'POST',
        body: fd
      });
      if (!isCurrentUploadContext(request)) {
        return;
      }
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const text = await res.text();
      if (!isCurrentUploadContext(request)) {
        return;
      }
      let json;
      try {
        json = JSON.parse(text);
      } catch (err) {
        throw new Error(text || 'Invalid server response.');
      }
      if (!json.success) {
        if (errDiv) {
          errDiv.textContent = json.data && json.data.message || 'Upload failed.';
          errDiv.style.display = '';
        }
        return;
      }
      const uploaded = normaliseClipart(json.data);
      if (!clipart.some(item => item.id === uploaded.id)) {
        clipart.push(uploaded);
      }
      updateClipartGridUI();
      if (nameInput) {
        nameInput.value = '';
      }
      closeModal();
    } catch (err) {
      if (errDiv && isCurrentUploadContext(request)) {
        errDiv.textContent = err?.message || 'Upload failed. Please try again.';
        errDiv.style.display = '';
      }
    } finally {
      if (uploadWrite === request) {
        uploadWrite = null;
        syncUploadWriteControls();
      }
    }
  });
}

// ---------------------------------------------------------------------------
// Edit modal (rename)
// ---------------------------------------------------------------------------

function isEditContextCurrent(request) {
  const modal = document.getElementById('oc-clipart-modal');
  return editWrite === request && request.generation === editModalGeneration && editClipartId === request.id && !modal?.hidden;
}
function syncEditWriteControls() {
  const saveBtn = document.getElementById('oc-clipart-save-btn');
  const deleteBtn = document.getElementById('oc-clipart-delete-btn');
  const busy = !!editWrite;
  if (saveBtn) {
    saveBtn.dataset.label ||= saveBtn.textContent;
    saveBtn.disabled = busy;
    saveBtn.setAttribute('aria-disabled', busy ? 'true' : 'false');
    saveBtn.textContent = busy ? 'Saving...' : saveBtn.dataset.label;
  }
  if (deleteBtn) {
    deleteBtn.disabled = busy;
    deleteBtn.setAttribute('aria-disabled', busy ? 'true' : 'false');
  }
}
function openEditModal(id) {
  const item = clipart.find(c => c.id === id);
  if (!item) {
    return;
  }
  editModalGeneration++;
  editWrite = null;
  editClipartId = id;
  const modal = document.getElementById('oc-clipart-modal');
  const nameInp = document.getElementById('oc_clipart_name');
  const preview = document.getElementById('oc-clipart-modal-preview-img');
  const errDiv = document.getElementById('oc-clipart-error');
  const delBtn = document.getElementById('oc-clipart-delete-btn');
  const colourChangeable = document.getElementById('oc_clipart_colour_changeable');
  if (nameInp) {
    nameInp.value = item.name;
  }
  if (preview) {
    preview.src = item.url;
    preview.alt = item.name;
  }
  if (errDiv) {
    errDiv.style.display = 'none';
    errDiv.textContent = '';
  }
  if (colourChangeable) {
    colourChangeable.checked = !!item.colourChangeable;
  }
  setCheckedMethods('.oc-clipart-method-check', item.allowedPrintMethods);
  if (delBtn) {
    delBtn.style.display = '';
  }
  if (modal) {
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    syncEditWriteControls();
    if (nameInp) {
      nameInp.focus();
    }
  }
}
function initEditModal() {
  const modal = document.getElementById('oc-clipart-modal');
  const closeBtn = document.getElementById('oc-clipart-modal-close');
  const cancelBtn = document.getElementById('oc-clipart-cancel-btn');
  const saveBtn = document.getElementById('oc-clipart-save-btn');
  const deleteBtn = document.getElementById('oc-clipart-delete-btn');
  const nameInput = document.getElementById('oc_clipart_name');
  const colourChangeable = document.getElementById('oc_clipart_colour_changeable');
  const errDiv = document.getElementById('oc-clipart-error');
  if (!modal) {
    return;
  }
  function closeModal() {
    editModalGeneration++;
    editWrite = null;
    modal.hidden = true;
    document.body.style.overflow = '';
    editClipartId = null;
    syncEditWriteControls();
  }
  closeBtn?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', e => {
    if (e.target === modal) {
      closeModal();
    }
  });
  deleteBtn?.addEventListener('click', () => {
    if (!editClipartId || editWrite) {
      return;
    }
    const item = clipart.find(c => c.id === editClipartId);
    if (item && confirm('Delete this clipart?')) {
      window.location.href = item.deleteUrl;
    }
  });
  saveBtn?.addEventListener('click', async () => {
    if (!editClipartId || editWrite) {
      return;
    }
    const targetId = editClipartId;
    const name = nameInput && nameInput.value.trim() || '';
    if (!name) {
      if (nameInput) {
        nameInput.focus();
      }
      return;
    }
    const canChangeColour = colourChangeable?.checked;
    const methods = checkedMethods('.oc-clipart-method-check');
    const request = {
      generation: editModalGeneration,
      id: targetId,
      mode: 'edit'
    };
    const body = new URLSearchParams({
      action: 'oc_clipart_rename',
      nonce: window.ocClipartNonce,
      id: targetId,
      name,
      colour_changeable: canChangeColour ? '1' : '0'
    });
    methods.forEach(method => body.append('allowed_print_methods[]', method));
    editWrite = request;
    syncEditWriteControls();
    try {
      const res = await fetch(window.ocAjaxUrl, {
        method: 'POST',
        body
      });
      if (!isEditContextCurrent(request)) {
        return;
      }
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const json = await res.json();
      if (!isEditContextCurrent(request)) {
        return;
      }
      if (!json.success) {
        if (errDiv) {
          errDiv.textContent = json.data && json.data.message || 'Save failed.';
          errDiv.style.display = '';
        }
        return;
      }
      const idx = clipart.findIndex(c => c.id === request.id);
      if (idx !== -1) {
        clipart[idx] = normaliseClipart({
          ...clipart[idx],
          ...json.data,
          name
        });
        updateClipartGridUI();
      }
      closeModal();
    } catch (e) {
      if (isEditContextCurrent(request)) {
        console.warn('[OC] Clipart rename failed:', e);
      }
      if (errDiv && isEditContextCurrent(request)) {
        errDiv.textContent = 'Save failed. Please try again.';
        errDiv.style.display = '';
      }
    } finally {
      if (editWrite === request) {
        editWrite = null;
        syncEditWriteControls();
      }
    }
  });

  // Wire up server-rendered cards and append additional cards on demand.
  document.querySelectorAll('.oc-clipart-card').forEach(bindClipartCard);
  document.getElementById('oc-clipart-load-more')?.addEventListener('click', function () {
    const grid = document.getElementById('oc-clipart-grid');
    const step = Number(this.dataset.step || 60);
    if (!grid) {
      return;
    }
    const renderedIds = new Set([...grid.querySelectorAll('.oc-clipart-card')].map(card => Number(card.dataset.clipartId)));
    clipart.filter(item => !renderedIds.has(item.id)).slice(0, step).forEach(item => {
      grid.appendChild(buildClipartCardEl(item));
      renderedIds.add(item.id);
    });
    this.dataset.offset = String(renderedIds.size);
    if (renderedIds.size >= clipart.length) {
      this.parentElement?.remove();
    }
  });
}

// ---------------------------------------------------------------------------
// Group card grid
// ---------------------------------------------------------------------------

function clipartById(id) {
  return clipart.find(c => c.id === id);
}
function buildGroupCardEl(group) {
  const card = document.createElement('div');
  card.className = 'oc-group-card oc-clipart-group-card';
  card.dataset.groupId = group.id;
  card.dataset.groupName = group.name;
  card.setAttribute('role', 'button');
  card.setAttribute('tabindex', '0');
  let thumbsHtml = group.clipartIds.slice(0, 6).map(cid => {
    const c = clipartById(cid);
    if (!c) {
      return '';
    }
    return '<div class="oc-clipart-thumb" title="' + h(c.name) + '"><img src="' + h(c.url) + '" alt="' + h(c.name) + '" /></div>';
  }).join('');
  if (group.clipartIds.length > 6) {
    thumbsHtml += '<span class="oc-group-card-more">+' + (group.clipartIds.length - 6) + '</span>';
  }
  if (group.clipartIds.length === 0) {
    thumbsHtml = '<span style="color:var(--oc-gray-400);font-size:12px;">Empty group</span>';
  }
  card.innerHTML = '<div class="oc-group-card-body">' + '<p class="oc-group-card-name">' + h(group.name) + '</p>' + '<p class="oc-group-card-count">' + group.clipartIds.length + ' ' + (1 === group.clipartIds.length ? 'item' : 'items') + '</p>' + '<div class="oc-clipart-group-thumbs">' + thumbsHtml + '</div>' + '</div>';
  card.addEventListener('click', () => openGroupModal(group.id));
  card.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openGroupModal(group.id);
    }
  });
  return card;
}
function updateGroupGridUI() {
  const grid = document.getElementById('oc-clipart-group-grid');
  const empty = document.getElementById('oc-clipart-groups-empty');
  const count = document.getElementById('oc-clipart-groups-count');
  const tab = document.querySelector('.oc-tab[data-target="oc-tab-clipart-groups"] .oc-tab-count');
  if (!grid) {
    return;
  }
  if (count) {
    count.textContent = groups.length + ' ' + (1 === groups.length ? 'group' : 'groups');
  }
  if (tab) {
    tab.textContent = groups.length;
  }
  if (groups.length === 0) {
    if (empty) {
      empty.style.display = '';
    }
    grid.style.display = 'none';
    return;
  }
  if (empty) {
    empty.style.display = 'none';
  }
  grid.style.display = '';
  grid.innerHTML = '';
  groups.forEach(g => grid.appendChild(buildGroupCardEl(g)));
}

// ---------------------------------------------------------------------------
// Group modal
// ---------------------------------------------------------------------------

const groupModal = () => document.getElementById('oc-clipart-group-modal');
const groupNameInput = () => document.getElementById('oc-clipart-group-name-input');
const groupPicker = () => document.getElementById('oc-clipart-group-picker');
const groupSelCount = () => document.getElementById('oc-clipart-group-selected-count');
const groupDeleteBtn = () => document.getElementById('oc-clipart-group-delete-btn');
function isGroupContextCurrent(request) {
  return groupWrite === request && request.generation === groupModalGeneration && editGroupId === request.id && !groupModal()?.hidden;
}
function syncGroupWriteControls() {
  const saveBtn = document.getElementById('oc-clipart-group-save-btn');
  const deleteBtn = groupDeleteBtn();
  const busy = !!groupWrite;
  if (saveBtn) {
    saveBtn.dataset.label ||= saveBtn.textContent;
    saveBtn.disabled = busy;
    saveBtn.setAttribute('aria-disabled', busy ? 'true' : 'false');
    saveBtn.textContent = busy ? 'Saving...' : saveBtn.dataset.label;
  }
  if (deleteBtn) {
    deleteBtn.disabled = busy;
    deleteBtn.setAttribute('aria-disabled', busy ? 'true' : 'false');
  }
}
function openGroupModal(id) {
  groupModalGeneration++;
  groupWrite = null;
  editGroupId = id || null;
  const group = id ? groups.find(g => g.id === id) : null;
  groupNameInput().value = group ? group.name : '';
  const deleteBtn = groupDeleteBtn();
  if (deleteBtn) {
    deleteBtn.style.display = group ? '' : 'none';
  }
  renderClipartPicker(group ? group.clipartIds : []);
  groupModal().hidden = false;
  document.body.style.overflow = 'hidden';
  syncGroupWriteControls();
  groupNameInput().focus();
}
function closeGroupModal() {
  groupModalGeneration++;
  groupWrite = null;
  groupModal().hidden = true;
  document.body.style.overflow = '';
  editGroupId = null;
  syncGroupWriteControls();
}
function renderClipartPicker(selectedIds) {
  const picker = groupPicker();
  if (!picker) {
    return;
  }
  picker.innerHTML = '';
  clipart.forEach(item => {
    const checked = selectedIds.includes(item.id);
    const label = document.createElement('label');
    label.className = 'oc-group-font-item';
    label.innerHTML = '<input type="checkbox" value="' + item.id + '"' + (checked ? ' checked' : '') + ' />' + '<div class="oc-clipart-thumb oc-clipart-picker-thumb" title="' + h(item.name) + '">' + '<img src="' + h(item.url) + '" alt="' + h(item.name) + '" />' + '</div>' + '<span class="oc-group-font-info">' + '<span class="oc-group-font-info-name">' + h(item.name) + '</span>' + '<span class="oc-group-font-info-meta">' + h(item.fileType.toUpperCase()) + '</span>' + '</span>';
    label.querySelector('input').addEventListener('change', updateGroupSelCount);
    picker.appendChild(label);
  });
  updateGroupSelCount();
}
function updateGroupSelCount() {
  const n = groupPicker() ? groupPicker().querySelectorAll('input:checked').length : 0;
  if (groupSelCount()) {
    groupSelCount().textContent = n + ' selected';
  }
}
function selectedClipartIds() {
  const picker = groupPicker();
  if (!picker) {
    return [];
  }
  return Array.from(picker.querySelectorAll('input:checked')).map(cb => Number(cb.value));
}
async function saveGroup() {
  if (groupWrite) {
    return;
  }
  const name = groupNameInput().value.trim();
  const clipartIds = selectedClipartIds();
  if (!name) {
    groupNameInput().focus();
    return;
  }
  const targetId = editGroupId;
  const mode = targetId ? 'edit' : 'create';
  const request = {
    generation: groupModalGeneration,
    id: targetId,
    mode
  };
  const action = mode === 'edit' ? 'oc_clipart_group_update' : 'oc_clipart_group_create';
  const body = new URLSearchParams({
    action,
    nonce: window.ocClipartNonce,
    name,
    id: targetId || 0
  });
  clipartIds.forEach(id => body.append('clipart_ids[]', id));
  groupWrite = request;
  syncGroupWriteControls();
  try {
    const res = await fetch(window.ocAjaxUrl, {
      method: 'POST',
      body
    });
    if (!isGroupContextCurrent(request)) {
      return;
    }
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const json = await res.json();
    if (!isGroupContextCurrent(request)) {
      return;
    }
    if (!json.success) {
      alert(json.data?.message || 'Save failed.');
      return;
    }
    const saved = normaliseGroup(json.data);
    if (request.mode === 'edit') {
      const idx = groups.findIndex(g => g.id === request.id);
      if (idx !== -1) {
        groups[idx] = saved;
      }
    } else if (!groups.some(group => group.id === saved.id)) {
      groups.push(saved);
    }
    updateGroupGridUI();
    closeGroupModal();
  } catch (e) {
    if (isGroupContextCurrent(request)) {
      console.warn('[OC] Clipart group save failed:', e);
      alert('Save failed. Please try again.');
    }
  } finally {
    if (groupWrite === request) {
      groupWrite = null;
      syncGroupWriteControls();
    }
  }
}
async function deleteGroup() {
  if (!editGroupId || groupWrite) {
    return;
  }
  if (!confirm('Delete this clipart group?')) {
    return;
  }
  const targetId = editGroupId;
  const request = {
    generation: groupModalGeneration,
    id: targetId,
    mode: 'delete'
  };
  const body = new URLSearchParams({
    action: 'oc_clipart_group_delete',
    nonce: window.ocClipartNonce,
    id: targetId
  });
  groupWrite = request;
  syncGroupWriteControls();
  try {
    const res = await fetch(window.ocAjaxUrl, {
      method: 'POST',
      body
    });
    if (!isGroupContextCurrent(request)) {
      return;
    }
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const json = await res.json();
    if (!isGroupContextCurrent(request)) {
      return;
    }
    if (!json.success) {
      alert(json.data?.message || 'Delete failed.');
      return;
    }
    groups = groups.filter(g => g.id !== request.id);
    updateGroupGridUI();
    closeGroupModal();
  } catch (e) {
    if (isGroupContextCurrent(request)) {
      console.warn('[OC] Clipart group delete failed:', e);
      alert('Delete failed. Please try again.');
    }
  } finally {
    if (groupWrite === request) {
      groupWrite = null;
      syncGroupWriteControls();
    }
  }
}
function initGroupModal() {
  document.getElementById('oc-create-clipart-group-btn')?.addEventListener('click', () => openGroupModal(null));
  document.getElementById('oc-clipart-group-modal-close')?.addEventListener('click', closeGroupModal);
  document.getElementById('oc-clipart-group-cancel-btn')?.addEventListener('click', closeGroupModal);
  groupModal()?.addEventListener('click', e => {
    if (e.target === groupModal()) {
      closeGroupModal();
    }
  });
  document.getElementById('oc-clipart-group-save-btn')?.addEventListener('click', saveGroup);
  groupDeleteBtn()?.addEventListener('click', deleteGroup);
  document.querySelectorAll('.oc-clipart-group-card').forEach(card => {
    card.addEventListener('click', () => openGroupModal(Number(card.dataset.groupId)));
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openGroupModal(Number(card.dataset.groupId));
      }
    });
  });
}

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initUploadModal();
  initEditModal();
  initGroupModal();
});
})();

/******/ })()
;