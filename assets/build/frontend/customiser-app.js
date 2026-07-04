/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@transloadit/prettier-bytes/dist/prettierBytes.js"
/*!************************************************************************!*\
  !*** ./node_modules/@transloadit/prettier-bytes/dist/prettierBytes.js ***!
  \************************************************************************/
(module) {

"use strict";

module.exports = function prettierBytes(input) {
    if (typeof input !== 'number' || Number.isNaN(input)) {
        throw new TypeError(`Expected a number, got ${typeof input}`);
    }
    const neg = input < 0;
    let num = Math.abs(input);
    if (neg) {
        num = -num;
    }
    if (num === 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    const exponent = Math.min(Math.floor(Math.log(num) / Math.log(1024)), units.length - 1);
    const value = Number(num / 1024 ** exponent);
    const unit = units[exponent];
    return `${value >= 10 || value % 1 === 0 ? Math.round(value) : value.toFixed(1)} ${unit}`;
};
//# sourceMappingURL=prettierBytes.js.map

/***/ },

/***/ "./node_modules/lodash/_Symbol.js"
/*!****************************************!*\
  !*** ./node_modules/lodash/_Symbol.js ***!
  \****************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var root = __webpack_require__(/*! ./_root */ "./node_modules/lodash/_root.js");

/** Built-in value references. */
var Symbol = root.Symbol;

module.exports = Symbol;


/***/ },

/***/ "./node_modules/lodash/_baseGetTag.js"
/*!********************************************!*\
  !*** ./node_modules/lodash/_baseGetTag.js ***!
  \********************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var Symbol = __webpack_require__(/*! ./_Symbol */ "./node_modules/lodash/_Symbol.js"),
    getRawTag = __webpack_require__(/*! ./_getRawTag */ "./node_modules/lodash/_getRawTag.js"),
    objectToString = __webpack_require__(/*! ./_objectToString */ "./node_modules/lodash/_objectToString.js");

/** `Object#toString` result references. */
var nullTag = '[object Null]',
    undefinedTag = '[object Undefined]';

/** Built-in value references. */
var symToStringTag = Symbol ? Symbol.toStringTag : undefined;

/**
 * The base implementation of `getTag` without fallbacks for buggy environments.
 *
 * @private
 * @param {*} value The value to query.
 * @returns {string} Returns the `toStringTag`.
 */
function baseGetTag(value) {
  if (value == null) {
    return value === undefined ? undefinedTag : nullTag;
  }
  return (symToStringTag && symToStringTag in Object(value))
    ? getRawTag(value)
    : objectToString(value);
}

module.exports = baseGetTag;


/***/ },

/***/ "./node_modules/lodash/_baseTrim.js"
/*!******************************************!*\
  !*** ./node_modules/lodash/_baseTrim.js ***!
  \******************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var trimmedEndIndex = __webpack_require__(/*! ./_trimmedEndIndex */ "./node_modules/lodash/_trimmedEndIndex.js");

/** Used to match leading whitespace. */
var reTrimStart = /^\s+/;

/**
 * The base implementation of `_.trim`.
 *
 * @private
 * @param {string} string The string to trim.
 * @returns {string} Returns the trimmed string.
 */
function baseTrim(string) {
  return string
    ? string.slice(0, trimmedEndIndex(string) + 1).replace(reTrimStart, '')
    : string;
}

module.exports = baseTrim;


/***/ },

/***/ "./node_modules/lodash/_freeGlobal.js"
/*!********************************************!*\
  !*** ./node_modules/lodash/_freeGlobal.js ***!
  \********************************************/
(module) {

/** Detect free variable `global` from Node.js. */
var freeGlobal = typeof globalThis == 'object' && globalThis && globalThis.Object === Object && globalThis;

module.exports = freeGlobal;


/***/ },

/***/ "./node_modules/lodash/_getRawTag.js"
/*!*******************************************!*\
  !*** ./node_modules/lodash/_getRawTag.js ***!
  \*******************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var Symbol = __webpack_require__(/*! ./_Symbol */ "./node_modules/lodash/_Symbol.js");

/** Used for built-in method references. */
var objectProto = Object.prototype;

/** Used to check objects for own properties. */
var hasOwnProperty = objectProto.hasOwnProperty;

/**
 * Used to resolve the
 * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)
 * of values.
 */
var nativeObjectToString = objectProto.toString;

/** Built-in value references. */
var symToStringTag = Symbol ? Symbol.toStringTag : undefined;

/**
 * A specialized version of `baseGetTag` which ignores `Symbol.toStringTag` values.
 *
 * @private
 * @param {*} value The value to query.
 * @returns {string} Returns the raw `toStringTag`.
 */
function getRawTag(value) {
  var isOwn = hasOwnProperty.call(value, symToStringTag),
      tag = value[symToStringTag];

  try {
    value[symToStringTag] = undefined;
    var unmasked = true;
  } catch (e) {}

  var result = nativeObjectToString.call(value);
  if (unmasked) {
    if (isOwn) {
      value[symToStringTag] = tag;
    } else {
      delete value[symToStringTag];
    }
  }
  return result;
}

module.exports = getRawTag;


/***/ },

/***/ "./node_modules/lodash/_objectToString.js"
/*!************************************************!*\
  !*** ./node_modules/lodash/_objectToString.js ***!
  \************************************************/
(module) {

/** Used for built-in method references. */
var objectProto = Object.prototype;

/**
 * Used to resolve the
 * [`toStringTag`](http://ecma-international.org/ecma-262/7.0/#sec-object.prototype.tostring)
 * of values.
 */
var nativeObjectToString = objectProto.toString;

/**
 * Converts `value` to a string using `Object.prototype.toString`.
 *
 * @private
 * @param {*} value The value to convert.
 * @returns {string} Returns the converted string.
 */
function objectToString(value) {
  return nativeObjectToString.call(value);
}

module.exports = objectToString;


/***/ },

/***/ "./node_modules/lodash/_root.js"
/*!**************************************!*\
  !*** ./node_modules/lodash/_root.js ***!
  \**************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var freeGlobal = __webpack_require__(/*! ./_freeGlobal */ "./node_modules/lodash/_freeGlobal.js");

/** Detect free variable `self`. */
var freeSelf = typeof self == 'object' && self && self.Object === Object && self;

/** Used as a reference to the global object. */
var root = freeGlobal || freeSelf || Function('return this')();

module.exports = root;


/***/ },

/***/ "./node_modules/lodash/_trimmedEndIndex.js"
/*!*************************************************!*\
  !*** ./node_modules/lodash/_trimmedEndIndex.js ***!
  \*************************************************/
(module) {

/** Used to match a single whitespace character. */
var reWhitespace = /\s/;

/**
 * Used by `_.trim` and `_.trimEnd` to get the index of the last non-whitespace
 * character of `string`.
 *
 * @private
 * @param {string} string The string to inspect.
 * @returns {number} Returns the index of the last non-whitespace character.
 */
function trimmedEndIndex(string) {
  var index = string.length;

  while (index-- && reWhitespace.test(string.charAt(index))) {}
  return index;
}

module.exports = trimmedEndIndex;


/***/ },

/***/ "./node_modules/lodash/debounce.js"
/*!*****************************************!*\
  !*** ./node_modules/lodash/debounce.js ***!
  \*****************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var isObject = __webpack_require__(/*! ./isObject */ "./node_modules/lodash/isObject.js"),
    now = __webpack_require__(/*! ./now */ "./node_modules/lodash/now.js"),
    toNumber = __webpack_require__(/*! ./toNumber */ "./node_modules/lodash/toNumber.js");

/** Error message constants. */
var FUNC_ERROR_TEXT = 'Expected a function';

/* Built-in method references for those with the same name as other `lodash` methods. */
var nativeMax = Math.max,
    nativeMin = Math.min;

/**
 * Creates a debounced function that delays invoking `func` until after `wait`
 * milliseconds have elapsed since the last time the debounced function was
 * invoked. The debounced function comes with a `cancel` method to cancel
 * delayed `func` invocations and a `flush` method to immediately invoke them.
 * Provide `options` to indicate whether `func` should be invoked on the
 * leading and/or trailing edge of the `wait` timeout. The `func` is invoked
 * with the last arguments provided to the debounced function. Subsequent
 * calls to the debounced function return the result of the last `func`
 * invocation.
 *
 * **Note:** If `leading` and `trailing` options are `true`, `func` is
 * invoked on the trailing edge of the timeout only if the debounced function
 * is invoked more than once during the `wait` timeout.
 *
 * If `wait` is `0` and `leading` is `false`, `func` invocation is deferred
 * until to the next tick, similar to `setTimeout` with a timeout of `0`.
 *
 * See [David Corbacho's article](https://css-tricks.com/debouncing-throttling-explained-examples/)
 * for details over the differences between `_.debounce` and `_.throttle`.
 *
 * @static
 * @memberOf _
 * @since 0.1.0
 * @category Function
 * @param {Function} func The function to debounce.
 * @param {number} [wait=0] The number of milliseconds to delay.
 * @param {Object} [options={}] The options object.
 * @param {boolean} [options.leading=false]
 *  Specify invoking on the leading edge of the timeout.
 * @param {number} [options.maxWait]
 *  The maximum time `func` is allowed to be delayed before it's invoked.
 * @param {boolean} [options.trailing=true]
 *  Specify invoking on the trailing edge of the timeout.
 * @returns {Function} Returns the new debounced function.
 * @example
 *
 * // Avoid costly calculations while the window size is in flux.
 * jQuery(window).on('resize', _.debounce(calculateLayout, 150));
 *
 * // Invoke `sendMail` when clicked, debouncing subsequent calls.
 * jQuery(element).on('click', _.debounce(sendMail, 300, {
 *   'leading': true,
 *   'trailing': false
 * }));
 *
 * // Ensure `batchLog` is invoked once after 1 second of debounced calls.
 * var debounced = _.debounce(batchLog, 250, { 'maxWait': 1000 });
 * var source = new EventSource('/stream');
 * jQuery(source).on('message', debounced);
 *
 * // Cancel the trailing debounced invocation.
 * jQuery(window).on('popstate', debounced.cancel);
 */
function debounce(func, wait, options) {
  var lastArgs,
      lastThis,
      maxWait,
      result,
      timerId,
      lastCallTime,
      lastInvokeTime = 0,
      leading = false,
      maxing = false,
      trailing = true;

  if (typeof func != 'function') {
    throw new TypeError(FUNC_ERROR_TEXT);
  }
  wait = toNumber(wait) || 0;
  if (isObject(options)) {
    leading = !!options.leading;
    maxing = 'maxWait' in options;
    maxWait = maxing ? nativeMax(toNumber(options.maxWait) || 0, wait) : maxWait;
    trailing = 'trailing' in options ? !!options.trailing : trailing;
  }

  function invokeFunc(time) {
    var args = lastArgs,
        thisArg = lastThis;

    lastArgs = lastThis = undefined;
    lastInvokeTime = time;
    result = func.apply(thisArg, args);
    return result;
  }

  function leadingEdge(time) {
    // Reset any `maxWait` timer.
    lastInvokeTime = time;
    // Start the timer for the trailing edge.
    timerId = setTimeout(timerExpired, wait);
    // Invoke the leading edge.
    return leading ? invokeFunc(time) : result;
  }

  function remainingWait(time) {
    var timeSinceLastCall = time - lastCallTime,
        timeSinceLastInvoke = time - lastInvokeTime,
        timeWaiting = wait - timeSinceLastCall;

    return maxing
      ? nativeMin(timeWaiting, maxWait - timeSinceLastInvoke)
      : timeWaiting;
  }

  function shouldInvoke(time) {
    var timeSinceLastCall = time - lastCallTime,
        timeSinceLastInvoke = time - lastInvokeTime;

    // Either this is the first call, activity has stopped and we're at the
    // trailing edge, the system time has gone backwards and we're treating
    // it as the trailing edge, or we've hit the `maxWait` limit.
    return (lastCallTime === undefined || (timeSinceLastCall >= wait) ||
      (timeSinceLastCall < 0) || (maxing && timeSinceLastInvoke >= maxWait));
  }

  function timerExpired() {
    var time = now();
    if (shouldInvoke(time)) {
      return trailingEdge(time);
    }
    // Restart the timer.
    timerId = setTimeout(timerExpired, remainingWait(time));
  }

  function trailingEdge(time) {
    timerId = undefined;

    // Only invoke if we have `lastArgs` which means `func` has been
    // debounced at least once.
    if (trailing && lastArgs) {
      return invokeFunc(time);
    }
    lastArgs = lastThis = undefined;
    return result;
  }

  function cancel() {
    if (timerId !== undefined) {
      clearTimeout(timerId);
    }
    lastInvokeTime = 0;
    lastArgs = lastCallTime = lastThis = timerId = undefined;
  }

  function flush() {
    return timerId === undefined ? result : trailingEdge(now());
  }

  function debounced() {
    var time = now(),
        isInvoking = shouldInvoke(time);

    lastArgs = arguments;
    lastThis = this;
    lastCallTime = time;

    if (isInvoking) {
      if (timerId === undefined) {
        return leadingEdge(lastCallTime);
      }
      if (maxing) {
        // Handle invocations in a tight loop.
        clearTimeout(timerId);
        timerId = setTimeout(timerExpired, wait);
        return invokeFunc(lastCallTime);
      }
    }
    if (timerId === undefined) {
      timerId = setTimeout(timerExpired, wait);
    }
    return result;
  }
  debounced.cancel = cancel;
  debounced.flush = flush;
  return debounced;
}

module.exports = debounce;


/***/ },

/***/ "./node_modules/lodash/isObject.js"
/*!*****************************************!*\
  !*** ./node_modules/lodash/isObject.js ***!
  \*****************************************/
(module) {

/**
 * Checks if `value` is the
 * [language type](http://www.ecma-international.org/ecma-262/7.0/#sec-ecmascript-language-types)
 * of `Object`. (e.g. arrays, functions, objects, regexes, `new Number(0)`, and `new String('')`)
 *
 * @static
 * @memberOf _
 * @since 0.1.0
 * @category Lang
 * @param {*} value The value to check.
 * @returns {boolean} Returns `true` if `value` is an object, else `false`.
 * @example
 *
 * _.isObject({});
 * // => true
 *
 * _.isObject([1, 2, 3]);
 * // => true
 *
 * _.isObject(_.noop);
 * // => true
 *
 * _.isObject(null);
 * // => false
 */
function isObject(value) {
  var type = typeof value;
  return value != null && (type == 'object' || type == 'function');
}

module.exports = isObject;


/***/ },

/***/ "./node_modules/lodash/isObjectLike.js"
/*!*********************************************!*\
  !*** ./node_modules/lodash/isObjectLike.js ***!
  \*********************************************/
(module) {

/**
 * Checks if `value` is object-like. A value is object-like if it's not `null`
 * and has a `typeof` result of "object".
 *
 * @static
 * @memberOf _
 * @since 4.0.0
 * @category Lang
 * @param {*} value The value to check.
 * @returns {boolean} Returns `true` if `value` is object-like, else `false`.
 * @example
 *
 * _.isObjectLike({});
 * // => true
 *
 * _.isObjectLike([1, 2, 3]);
 * // => true
 *
 * _.isObjectLike(_.noop);
 * // => false
 *
 * _.isObjectLike(null);
 * // => false
 */
function isObjectLike(value) {
  return value != null && typeof value == 'object';
}

module.exports = isObjectLike;


/***/ },

/***/ "./node_modules/lodash/isSymbol.js"
/*!*****************************************!*\
  !*** ./node_modules/lodash/isSymbol.js ***!
  \*****************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var baseGetTag = __webpack_require__(/*! ./_baseGetTag */ "./node_modules/lodash/_baseGetTag.js"),
    isObjectLike = __webpack_require__(/*! ./isObjectLike */ "./node_modules/lodash/isObjectLike.js");

/** `Object#toString` result references. */
var symbolTag = '[object Symbol]';

/**
 * Checks if `value` is classified as a `Symbol` primitive or object.
 *
 * @static
 * @memberOf _
 * @since 4.0.0
 * @category Lang
 * @param {*} value The value to check.
 * @returns {boolean} Returns `true` if `value` is a symbol, else `false`.
 * @example
 *
 * _.isSymbol(Symbol.iterator);
 * // => true
 *
 * _.isSymbol('abc');
 * // => false
 */
function isSymbol(value) {
  return typeof value == 'symbol' ||
    (isObjectLike(value) && baseGetTag(value) == symbolTag);
}

module.exports = isSymbol;


/***/ },

/***/ "./node_modules/lodash/now.js"
/*!************************************!*\
  !*** ./node_modules/lodash/now.js ***!
  \************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var root = __webpack_require__(/*! ./_root */ "./node_modules/lodash/_root.js");

/**
 * Gets the timestamp of the number of milliseconds that have elapsed since
 * the Unix epoch (1 January 1970 00:00:00 UTC).
 *
 * @static
 * @memberOf _
 * @since 2.4.0
 * @category Date
 * @returns {number} Returns the timestamp.
 * @example
 *
 * _.defer(function(stamp) {
 *   console.log(_.now() - stamp);
 * }, _.now());
 * // => Logs the number of milliseconds it took for the deferred invocation.
 */
var now = function() {
  return root.Date.now();
};

module.exports = now;


/***/ },

/***/ "./node_modules/lodash/throttle.js"
/*!*****************************************!*\
  !*** ./node_modules/lodash/throttle.js ***!
  \*****************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var debounce = __webpack_require__(/*! ./debounce */ "./node_modules/lodash/debounce.js"),
    isObject = __webpack_require__(/*! ./isObject */ "./node_modules/lodash/isObject.js");

/** Error message constants. */
var FUNC_ERROR_TEXT = 'Expected a function';

/**
 * Creates a throttled function that only invokes `func` at most once per
 * every `wait` milliseconds. The throttled function comes with a `cancel`
 * method to cancel delayed `func` invocations and a `flush` method to
 * immediately invoke them. Provide `options` to indicate whether `func`
 * should be invoked on the leading and/or trailing edge of the `wait`
 * timeout. The `func` is invoked with the last arguments provided to the
 * throttled function. Subsequent calls to the throttled function return the
 * result of the last `func` invocation.
 *
 * **Note:** If `leading` and `trailing` options are `true`, `func` is
 * invoked on the trailing edge of the timeout only if the throttled function
 * is invoked more than once during the `wait` timeout.
 *
 * If `wait` is `0` and `leading` is `false`, `func` invocation is deferred
 * until to the next tick, similar to `setTimeout` with a timeout of `0`.
 *
 * See [David Corbacho's article](https://css-tricks.com/debouncing-throttling-explained-examples/)
 * for details over the differences between `_.throttle` and `_.debounce`.
 *
 * @static
 * @memberOf _
 * @since 0.1.0
 * @category Function
 * @param {Function} func The function to throttle.
 * @param {number} [wait=0] The number of milliseconds to throttle invocations to.
 * @param {Object} [options={}] The options object.
 * @param {boolean} [options.leading=true]
 *  Specify invoking on the leading edge of the timeout.
 * @param {boolean} [options.trailing=true]
 *  Specify invoking on the trailing edge of the timeout.
 * @returns {Function} Returns the new throttled function.
 * @example
 *
 * // Avoid excessively updating the position while scrolling.
 * jQuery(window).on('scroll', _.throttle(updatePosition, 100));
 *
 * // Invoke `renewToken` when the click event is fired, but not more than once every 5 minutes.
 * var throttled = _.throttle(renewToken, 300000, { 'trailing': false });
 * jQuery(element).on('click', throttled);
 *
 * // Cancel the trailing throttled invocation.
 * jQuery(window).on('popstate', throttled.cancel);
 */
function throttle(func, wait, options) {
  var leading = true,
      trailing = true;

  if (typeof func != 'function') {
    throw new TypeError(FUNC_ERROR_TEXT);
  }
  if (isObject(options)) {
    leading = 'leading' in options ? !!options.leading : leading;
    trailing = 'trailing' in options ? !!options.trailing : trailing;
  }
  return debounce(func, wait, {
    'leading': leading,
    'maxWait': wait,
    'trailing': trailing
  });
}

module.exports = throttle;


/***/ },

/***/ "./node_modules/lodash/toNumber.js"
/*!*****************************************!*\
  !*** ./node_modules/lodash/toNumber.js ***!
  \*****************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var baseTrim = __webpack_require__(/*! ./_baseTrim */ "./node_modules/lodash/_baseTrim.js"),
    isObject = __webpack_require__(/*! ./isObject */ "./node_modules/lodash/isObject.js"),
    isSymbol = __webpack_require__(/*! ./isSymbol */ "./node_modules/lodash/isSymbol.js");

/** Used as references for various `Number` constants. */
var NAN = 0 / 0;

/** Used to detect bad signed hexadecimal string values. */
var reIsBadHex = /^[-+]0x[0-9a-f]+$/i;

/** Used to detect binary string values. */
var reIsBinary = /^0b[01]+$/i;

/** Used to detect octal string values. */
var reIsOctal = /^0o[0-7]+$/i;

/** Built-in method references without a dependency on `root`. */
var freeParseInt = parseInt;

/**
 * Converts `value` to a number.
 *
 * @static
 * @memberOf _
 * @since 4.0.0
 * @category Lang
 * @param {*} value The value to process.
 * @returns {number} Returns the number.
 * @example
 *
 * _.toNumber(3.2);
 * // => 3.2
 *
 * _.toNumber(Number.MIN_VALUE);
 * // => 5e-324
 *
 * _.toNumber(Infinity);
 * // => Infinity
 *
 * _.toNumber('3.2');
 * // => 3.2
 */
function toNumber(value) {
  if (typeof value == 'number') {
    return value;
  }
  if (isSymbol(value)) {
    return NAN;
  }
  if (isObject(value)) {
    var other = typeof value.valueOf == 'function' ? value.valueOf() : value;
    value = isObject(other) ? (other + '') : other;
  }
  if (typeof value != 'string') {
    return value === 0 ? value : +value;
  }
  value = baseTrim(value);
  var isBinary = reIsBinary.test(value);
  return (isBinary || reIsOctal.test(value))
    ? freeParseInt(value.slice(2), isBinary ? 2 : 8)
    : (reIsBadHex.test(value) ? NAN : +value);
}

module.exports = toNumber;


/***/ },

/***/ "./node_modules/mime-match/index.js"
/*!******************************************!*\
  !*** ./node_modules/mime-match/index.js ***!
  \******************************************/
(module, __unused_webpack_exports, __webpack_require__) {

var wildcard = __webpack_require__(/*! wildcard */ "./node_modules/wildcard/index.js");
var reMimePartSplit = /[\/\+\.]/;

/**
  # mime-match

  A simple function to checker whether a target mime type matches a mime-type
  pattern (e.g. image/jpeg matches image/jpeg OR image/*).

  ## Example Usage

  <<< example.js

**/
module.exports = function(target, pattern) {
  function test(pattern) {
    var result = wildcard(pattern, target, reMimePartSplit);

    // ensure that we have a valid mime type (should have two parts)
    return result && result.length >= 2;
  }

  return pattern ? test(pattern.split(';')[0]) : test;
};


/***/ },

/***/ "./node_modules/@uppy/core/dist/style.min.css"
/*!****************************************************!*\
  !*** ./node_modules/@uppy/core/dist/style.min.css ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/@uppy/drag-drop/dist/style.min.css"
/*!*********************************************************!*\
  !*** ./node_modules/@uppy/drag-drop/dist/style.min.css ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./src/frontend/customiser-app.scss"
/*!******************************************!*\
  !*** ./src/frontend/customiser-app.scss ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/namespace-emitter/index.js"
/*!*************************************************!*\
  !*** ./node_modules/namespace-emitter/index.js ***!
  \*************************************************/
(module) {

/**
* Create an event emitter with namespaces
* @name createNamespaceEmitter
* @example
* var emitter = require('./index')()
*
* emitter.on('*', function () {
*   console.log('all events emitted', this.event)
* })
*
* emitter.on('example', function () {
*   console.log('example event emitted')
* })
*/
module.exports = function createNamespaceEmitter () {
  var emitter = {}
  var _fns = emitter._fns = {}

  /**
  * Emit an event. Optionally namespace the event. Handlers are fired in the order in which they were added with exact matches taking precedence. Separate the namespace and event with a `:`
  * @name emit
  * @param {String} event – the name of the event, with optional namespace
  * @param {...*} data – up to 6 arguments that are passed to the event listener
  * @example
  * emitter.emit('example')
  * emitter.emit('demo:test')
  * emitter.emit('data', { example: true}, 'a string', 1)
  */
  emitter.emit = function emit (event, arg1, arg2, arg3, arg4, arg5, arg6) {
    var toEmit = getListeners(event)

    if (toEmit.length) {
      emitAll(event, toEmit, [arg1, arg2, arg3, arg4, arg5, arg6])
    }
  }

  /**
  * Create en event listener.
  * @name on
  * @param {String} event
  * @param {Function} fn
  * @example
  * emitter.on('example', function () {})
  * emitter.on('demo', function () {})
  */
  emitter.on = function on (event, fn) {
    if (!_fns[event]) {
      _fns[event] = []
    }

    _fns[event].push(fn)
  }

  /**
  * Create en event listener that fires once.
  * @name once
  * @param {String} event
  * @param {Function} fn
  * @example
  * emitter.once('example', function () {})
  * emitter.once('demo', function () {})
  */
  emitter.once = function once (event, fn) {
    function one () {
      fn.apply(this, arguments)
      emitter.off(event, one)
    }
    this.on(event, one)
  }

  /**
  * Stop listening to an event. Stop all listeners on an event by only passing the event name. Stop a single listener by passing that event handler as a callback.
  * You must be explicit about what will be unsubscribed: `emitter.off('demo')` will unsubscribe an `emitter.on('demo')` listener,
  * `emitter.off('demo:example')` will unsubscribe an `emitter.on('demo:example')` listener
  * @name off
  * @param {String} event
  * @param {Function} [fn] – the specific handler
  * @example
  * emitter.off('example')
  * emitter.off('demo', function () {})
  */
  emitter.off = function off (event, fn) {
    var keep = []

    if (event && fn) {
      var fns = this._fns[event]
      var i = 0
      var l = fns ? fns.length : 0

      for (i; i < l; i++) {
        if (fns[i] !== fn) {
          keep.push(fns[i])
        }
      }
    }

    keep.length ? this._fns[event] = keep : delete this._fns[event]
  }

  function getListeners (e) {
    var out = _fns[e] ? _fns[e] : []
    var idx = e.indexOf(':')
    var args = (idx === -1) ? [e] : [e.substring(0, idx), e.substring(idx + 1)]

    var keys = Object.keys(_fns)
    var i = 0
    var l = keys.length

    for (i; i < l; i++) {
      var key = keys[i]
      if (key === '*') {
        out = out.concat(_fns[key])
      }

      if (args.length === 2 && args[0] === key) {
        out = out.concat(_fns[key])
        break
      }
    }

    return out
  }

  function emitAll (e, fns, args) {
    var i = 0
    var l = fns.length

    for (i; i < l; i++) {
      if (!fns[i]) break
      fns[i].event = e
      fns[i].apply(fns[i], args)
    }
  }

  return emitter
}


/***/ },

/***/ "./node_modules/preact/compat/dist/compat.module.js"
/*!**********************************************************!*\
  !*** ./node_modules/preact/compat/dist/compat.module.js ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Children: () => (/* binding */ L),
/* harmony export */   Component: () => (/* reexport safe */ preact__WEBPACK_IMPORTED_MODULE_0__.Component),
/* harmony export */   Fragment: () => (/* reexport safe */ preact__WEBPACK_IMPORTED_MODULE_0__.Fragment),
/* harmony export */   PureComponent: () => (/* binding */ M),
/* harmony export */   StrictMode: () => (/* reexport safe */ preact__WEBPACK_IMPORTED_MODULE_0__.Fragment),
/* harmony export */   Suspense: () => (/* binding */ P),
/* harmony export */   SuspenseList: () => (/* binding */ B),
/* harmony export */   __SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED: () => (/* binding */ fn),
/* harmony export */   cloneElement: () => (/* binding */ mn),
/* harmony export */   createContext: () => (/* reexport safe */ preact__WEBPACK_IMPORTED_MODULE_0__.createContext),
/* harmony export */   createElement: () => (/* reexport safe */ preact__WEBPACK_IMPORTED_MODULE_0__.createElement),
/* harmony export */   createFactory: () => (/* binding */ sn),
/* harmony export */   createPortal: () => (/* binding */ $),
/* harmony export */   createRef: () => (/* reexport safe */ preact__WEBPACK_IMPORTED_MODULE_0__.createRef),
/* harmony export */   "default": () => (/* binding */ gn),
/* harmony export */   findDOMNode: () => (/* binding */ yn),
/* harmony export */   flushSync: () => (/* binding */ bn),
/* harmony export */   forwardRef: () => (/* binding */ D),
/* harmony export */   hydrate: () => (/* binding */ tn),
/* harmony export */   isElement: () => (/* binding */ Sn),
/* harmony export */   isFragment: () => (/* binding */ vn),
/* harmony export */   isMemo: () => (/* binding */ dn),
/* harmony export */   isValidElement: () => (/* binding */ hn),
/* harmony export */   lazy: () => (/* binding */ z),
/* harmony export */   memo: () => (/* binding */ N),
/* harmony export */   render: () => (/* binding */ nn),
/* harmony export */   startTransition: () => (/* binding */ x),
/* harmony export */   unmountComponentAtNode: () => (/* binding */ pn),
/* harmony export */   unstable_batchedUpdates: () => (/* binding */ _n),
/* harmony export */   useCallback: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useCallback),
/* harmony export */   useContext: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useContext),
/* harmony export */   useDebugValue: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useDebugValue),
/* harmony export */   useDeferredValue: () => (/* binding */ w),
/* harmony export */   useEffect: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useEffect),
/* harmony export */   useErrorBoundary: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useErrorBoundary),
/* harmony export */   useId: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useId),
/* harmony export */   useImperativeHandle: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useImperativeHandle),
/* harmony export */   useInsertionEffect: () => (/* binding */ I),
/* harmony export */   useLayoutEffect: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useLayoutEffect),
/* harmony export */   useMemo: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useMemo),
/* harmony export */   useReducer: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useReducer),
/* harmony export */   useRef: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useRef),
/* harmony export */   useState: () => (/* reexport safe */ preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useState),
/* harmony export */   useSyncExternalStore: () => (/* binding */ C),
/* harmony export */   useTransition: () => (/* binding */ k),
/* harmony export */   version: () => (/* binding */ an)
/* harmony export */ });
/* harmony import */ var preact__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! preact */ "./node_modules/preact/dist/preact.module.js");
/* harmony import */ var preact_hooks__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! preact/hooks */ "./node_modules/preact/hooks/dist/hooks.module.js");
function g(n,t){for(var e in t)n[e]=t[e];return n}function E(n,t){for(var e in n)if("__source"!==e&&!(e in t))return!0;for(var r in t)if("__source"!==r&&n[r]!==t[r])return!0;return!1}function C(n,t){var e=t(),r=(0,preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useState)({t:{__:e,u:t}}),u=r[0].t,o=r[1];return (0,preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useLayoutEffect)(function(){u.__=e,u.u=t,R(u)&&o({t:u})},[n,e,t]),(0,preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useEffect)(function(){return R(u)&&o({t:u}),n(function(){R(u)&&o({t:u})})},[n]),e}function R(n){try{return!((t=n.__)===(e=n.u())&&(0!==t||1/t==1/e)||t!=t&&e!=e)}catch(n){return!0}// removed by dead control flow
 var t, e; }function x(n){n()}function w(n){return n}function k(){return[!1,x]}var I=preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useLayoutEffect;function M(n,t){this.props=n,this.context=t}function N(n,e){function r(n){var t=this.props.ref;return t!=n.ref&&t&&("function"==typeof t?t(null):t.current=null),e?!e(this.props,n)||t!=n.ref:E(this.props,n)}function u(e){return this.shouldComponentUpdate=r,(0,preact__WEBPACK_IMPORTED_MODULE_0__.createElement)(n,e)}return u.displayName="Memo("+(n.displayName||n.name)+")",u.__f=u.prototype.isReactComponent=!0,u.type=n,u}(M.prototype=new preact__WEBPACK_IMPORTED_MODULE_0__.Component).isPureReactComponent=!0,M.prototype.shouldComponentUpdate=function(n,t){return E(this.props,n)||E(this.state,t)};var T=preact__WEBPACK_IMPORTED_MODULE_0__.options.__b;preact__WEBPACK_IMPORTED_MODULE_0__.options.__b=function(n){n.type&&n.type.__f&&n.ref&&(n.props.ref=n.ref,n.ref=null),T&&T(n)};var A="undefined"!=typeof Symbol&&Symbol.for&&Symbol.for("react.forward_ref")||3911;function D(n){function t(t){var e=g({},t);return delete e.ref,n(e,t.ref||null)}return t.$$typeof=A,t.render=n,t.prototype.isReactComponent=t.__f=!0,t.displayName="ForwardRef("+(n.displayName||n.name)+")",t}var F=function(n,t){return null==n?null:(0,preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray)((0,preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray)(n).map(t))},L={map:F,forEach:F,count:function(n){return n?(0,preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray)(n).length:0},only:function(n){var t=(0,preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray)(n);if(1!==t.length)throw"Children.only";return t[0]},toArray:preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray},O=preact__WEBPACK_IMPORTED_MODULE_0__.options.__e;preact__WEBPACK_IMPORTED_MODULE_0__.options.__e=function(n,t,e,r){if(n.then)for(var u,o=t;o=o.__;)if((u=o.__c)&&u.__c)return null==t.__e&&(t.__e=e.__e,t.__k=e.__k),u.__c(n,t);O(n,t,e,r)};var U=preact__WEBPACK_IMPORTED_MODULE_0__.options.unmount;function V(n,t,e){return n&&(n.__c&&n.__c.__H&&(n.__c.__H.__.forEach(function(n){"function"==typeof n.__c&&n.__c()}),n.__c.__H=null),null!=(n=g({},n)).__c&&(n.__c.__P===e&&(n.__c.__P=t),n.__c.__e=!0,n.__c=null),n.__k=n.__k&&n.__k.map(function(n){return V(n,t,e)})),n}function W(n,t,e){return n&&e&&(n.__v=null,n.__k=n.__k&&n.__k.map(function(n){return W(n,t,e)}),n.__c&&n.__c.__P===t&&(n.__e&&e.appendChild(n.__e),n.__c.__e=!0,n.__c.__P=e)),n}function P(){this.__u=0,this.o=null,this.__b=null}function j(n){var t=n.__&&n.__.__c;return t&&t.__a&&t.__a(n)}function z(n){var e,r,u,o=null;function i(i){if(e||(e=n()).then(function(n){n&&(o=n.default||n),u=!0},function(n){r=n,u=!0}),r)throw r;if(!u)throw e;return o?(0,preact__WEBPACK_IMPORTED_MODULE_0__.createElement)(o,i):null}return i.displayName="Lazy",i.__f=!0,i}function B(){this.i=null,this.l=null}preact__WEBPACK_IMPORTED_MODULE_0__.options.unmount=function(n){var t=n.__c;t&&(t.__z=!0),t&&t.__R&&t.__R(),t&&32&n.__u&&(n.type=null),U&&U(n)},(P.prototype=new preact__WEBPACK_IMPORTED_MODULE_0__.Component).__c=function(n,t){var e=t.__c,r=this;null==r.o&&(r.o=[]),r.o.push(e);var u=j(r.__v),o=!1,i=function(){o||r.__z||(o=!0,e.__R=null,u?u(c):c())};e.__R=i;var l=e.__P;e.__P=null;var c=function(){if(!--r.__u){if(r.state.__a){var n=r.state.__a;r.__v.__k[0]=W(n,n.__c.__P,n.__c.__O)}var t;for(r.setState({__a:r.__b=null});t=r.o.pop();)t.__P=l,t.forceUpdate()}};r.__u++||32&t.__u||r.setState({__a:r.__b=r.__v.__k[0]}),n.then(i,i)},P.prototype.componentWillUnmount=function(){this.o=[]},P.prototype.render=function(n,e){if(this.__b){if(this.__v.__k){var r=document.createElement("div"),o=this.__v.__k[0].__c;this.__v.__k[0]=V(this.__b,r,o.__O=o.__P)}this.__b=null}var i=e.__a&&(0,preact__WEBPACK_IMPORTED_MODULE_0__.createElement)(preact__WEBPACK_IMPORTED_MODULE_0__.Fragment,null,n.fallback);return i&&(i.__u&=-33),[(0,preact__WEBPACK_IMPORTED_MODULE_0__.createElement)(preact__WEBPACK_IMPORTED_MODULE_0__.Fragment,null,e.__a?null:n.children),i]};var H=function(n,t,e){if(++e[1]===e[0]&&n.l.delete(t),n.props.revealOrder&&("t"!==n.props.revealOrder[0]||!n.l.size))for(e=n.i;e;){for(;e.length>3;)e.pop()();if(e[1]<e[0])break;n.i=e=e[2]}};function Z(n){return this.getChildContext=function(){return n.context},n.children}function Y(n){var e=this,r=n.h;if(e.componentWillUnmount=function(){(0,preact__WEBPACK_IMPORTED_MODULE_0__.render)(null,e.v),e.v=null,e.h=null},e.h&&e.h!==r&&e.componentWillUnmount(),!e.v){for(var u=e.__v;null!==u&&!u.__m&&null!==u.__;)u=u.__;e.h=r,e.v={nodeType:1,parentNode:r,childNodes:[],__k:{__m:u.__m},contains:function(){return!0},namespaceURI:r.namespaceURI,insertBefore:function(n,t){this.childNodes.push(n),e.h.insertBefore(n,t)},removeChild:function(n){this.childNodes.splice(this.childNodes.indexOf(n)>>>1,1),e.h.removeChild(n)}}}(0,preact__WEBPACK_IMPORTED_MODULE_0__.render)((0,preact__WEBPACK_IMPORTED_MODULE_0__.createElement)(Z,{context:e.context},n.__v),e.v)}function $(n,e){var r=(0,preact__WEBPACK_IMPORTED_MODULE_0__.createElement)(Y,{__v:n,h:e});return r.containerInfo=e,r}(B.prototype=new preact__WEBPACK_IMPORTED_MODULE_0__.Component).__a=function(n){var t=this,e=j(t.__v),r=t.l.get(n);return r[0]++,function(u){var o=function(){t.props.revealOrder?(r.push(u),H(t,n,r)):u()};e?e(o):o()}},B.prototype.render=function(n){this.i=null,this.l=new Map;var t=(0,preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray)(n.children);n.revealOrder&&"b"===n.revealOrder[0]&&t.reverse();for(var e=t.length;e--;)this.l.set(t[e],this.i=[1,0,this.i]);return n.children},B.prototype.componentDidUpdate=B.prototype.componentDidMount=function(){var n=this;this.l.forEach(function(t,e){H(n,e,t)})};var q="undefined"!=typeof Symbol&&Symbol.for&&Symbol.for("react.element")||60103,G=/^(?:accent|alignment|arabic|baseline|cap|clip(?!PathU)|color|dominant|fill|flood|font|glyph(?!R)|horiz|image(!S)|letter|lighting|marker(?!H|W|U)|overline|paint|pointer|shape|stop|strikethrough|stroke|text(?!L)|transform|underline|unicode|units|v|vector|vert|word|writing|x(?!C))[A-Z]/,J=/^on(Ani|Tra|Tou|BeforeInp|Compo)/,K=/[A-Z0-9]/g,Q="undefined"!=typeof document,X=function(n){return("undefined"!=typeof Symbol&&"symbol"==typeof Symbol()?/fil|che|rad/:/fil|che|ra/).test(n)};function nn(n,t,e){return null==t.__k&&(t.textContent=""),(0,preact__WEBPACK_IMPORTED_MODULE_0__.render)(n,t),"function"==typeof e&&e(),n?n.__c:null}function tn(n,t,e){return (0,preact__WEBPACK_IMPORTED_MODULE_0__.hydrate)(n,t),"function"==typeof e&&e(),n?n.__c:null}preact__WEBPACK_IMPORTED_MODULE_0__.Component.prototype.isReactComponent=!0,["componentWillMount","componentWillReceiveProps","componentWillUpdate"].forEach(function(t){Object.defineProperty(preact__WEBPACK_IMPORTED_MODULE_0__.Component.prototype,t,{configurable:!0,get:function(){return this["UNSAFE_"+t]},set:function(n){Object.defineProperty(this,t,{configurable:!0,writable:!0,value:n})}})});var en=preact__WEBPACK_IMPORTED_MODULE_0__.options.event;preact__WEBPACK_IMPORTED_MODULE_0__.options.event=function(n){return en&&(n=en(n)),n.persist=function(){},n.isPropagationStopped=function(){return this.cancelBubble},n.isDefaultPrevented=function(){return this.defaultPrevented},n.nativeEvent=n};var rn,un={configurable:!0,get:function(){return this.class}},on=preact__WEBPACK_IMPORTED_MODULE_0__.options.vnode;preact__WEBPACK_IMPORTED_MODULE_0__.options.vnode=function(n){"string"==typeof n.type&&function(n){var t=n.props,e=n.type,u={},o=-1==e.indexOf("-");for(var i in t){var l=t[i];if(!("value"===i&&"defaultValue"in t&&null==l||Q&&"children"===i&&"noscript"===e||"class"===i||"className"===i)){var c=i.toLowerCase();"defaultValue"===i&&"value"in t&&null==t.value?i="value":"download"===i&&!0===l?l="":"translate"===c&&"no"===l?l=!1:"o"===c[0]&&"n"===c[1]?"ondoubleclick"===c?i="ondblclick":"onchange"!==c||"input"!==e&&"textarea"!==e||X(t.type)?"onfocus"===c?i="onfocusin":"onblur"===c?i="onfocusout":J.test(i)&&(i=c):c=i="oninput":o&&G.test(i)?i=i.replace(K,"-$&").toLowerCase():null===l&&(l=void 0),"oninput"===c&&u[i=c]&&(i="oninputCapture"),u[i]=l}}"select"==e&&(u.multiple&&Array.isArray(u.value)&&(u.value=(0,preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray)(t.children).forEach(function(n){n.props.selected=-1!=u.value.indexOf(n.props.value)})),null!=u.defaultValue&&(u.value=(0,preact__WEBPACK_IMPORTED_MODULE_0__.toChildArray)(t.children).forEach(function(n){n.props.selected=u.multiple?-1!=u.defaultValue.indexOf(n.props.value):u.defaultValue==n.props.value}))),t.class&&!t.className?(u.class=t.class,Object.defineProperty(u,"className",un)):t.className&&(u.class=u.className=t.className),n.props=u}(n),n.$$typeof=q,on&&on(n)};var ln=preact__WEBPACK_IMPORTED_MODULE_0__.options.__r;preact__WEBPACK_IMPORTED_MODULE_0__.options.__r=function(n){ln&&ln(n),rn=n.__c};var cn=preact__WEBPACK_IMPORTED_MODULE_0__.options.diffed;preact__WEBPACK_IMPORTED_MODULE_0__.options.diffed=function(n){cn&&cn(n);var t=n.props,e=n.__e;null!=e&&"textarea"===n.type&&"value"in t&&t.value!==e.value&&(e.value=null==t.value?"":t.value),rn=null};var fn={ReactCurrentDispatcher:{current:{readContext:function(n){return rn.__n[n.__c].props.value},useCallback:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useCallback,useContext:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useContext,useDebugValue:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useDebugValue,useDeferredValue:w,useEffect:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useEffect,useId:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useId,useImperativeHandle:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useImperativeHandle,useInsertionEffect:I,useLayoutEffect:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useLayoutEffect,useMemo:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useMemo,useReducer:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useReducer,useRef:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useRef,useState:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useState,useSyncExternalStore:C,useTransition:k}}},an="18.3.1";function sn(n){return preact__WEBPACK_IMPORTED_MODULE_0__.createElement.bind(null,n)}function hn(n){return!!n&&n.$$typeof===q}function vn(n){return hn(n)&&n.type===preact__WEBPACK_IMPORTED_MODULE_0__.Fragment}function dn(n){return!!n&&"string"==typeof n.displayName&&0==n.displayName.indexOf("Memo(")}function mn(n){return hn(n)?preact__WEBPACK_IMPORTED_MODULE_0__.cloneElement.apply(null,arguments):n}function pn(n){return!!n.__k&&((0,preact__WEBPACK_IMPORTED_MODULE_0__.render)(null,n),!0)}function yn(n){return n&&(n.base||1===n.nodeType&&n)||null}var _n=function(n,t){return n(t)},bn=function(n,t){var r=preact__WEBPACK_IMPORTED_MODULE_0__.options.debounceRendering;preact__WEBPACK_IMPORTED_MODULE_0__.options.debounceRendering=function(n){return n()};var u=n(t);return preact__WEBPACK_IMPORTED_MODULE_0__.options.debounceRendering=r,u},Sn=hn,gn={useState:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useState,useId:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useId,useReducer:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useReducer,useEffect:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useEffect,useLayoutEffect:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useLayoutEffect,useInsertionEffect:I,useTransition:k,useDeferredValue:w,useSyncExternalStore:C,startTransition:x,useRef:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useRef,useImperativeHandle:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useImperativeHandle,useMemo:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useMemo,useCallback:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useCallback,useContext:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useContext,useDebugValue:preact_hooks__WEBPACK_IMPORTED_MODULE_1__.useDebugValue,version:"18.3.1",Children:L,render:nn,hydrate:tn,unmountComponentAtNode:pn,createPortal:$,createElement:preact__WEBPACK_IMPORTED_MODULE_0__.createElement,createContext:preact__WEBPACK_IMPORTED_MODULE_0__.createContext,createFactory:sn,cloneElement:mn,createRef:preact__WEBPACK_IMPORTED_MODULE_0__.createRef,Fragment:preact__WEBPACK_IMPORTED_MODULE_0__.Fragment,isValidElement:hn,isElement:Sn,isFragment:vn,isMemo:dn,findDOMNode:yn,Component:preact__WEBPACK_IMPORTED_MODULE_0__.Component,PureComponent:M,memo:N,forwardRef:D,flushSync:bn,unstable_batchedUpdates:_n,StrictMode:preact__WEBPACK_IMPORTED_MODULE_0__.Fragment,Suspense:P,SuspenseList:B,lazy:z,__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED:fn};
//# sourceMappingURL=compat.module.js.map


/***/ },

/***/ "./node_modules/preact/dist/preact.module.js"
/*!***************************************************!*\
  !*** ./node_modules/preact/dist/preact.module.js ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Component: () => (/* binding */ x),
/* harmony export */   Fragment: () => (/* binding */ k),
/* harmony export */   cloneElement: () => (/* binding */ Q),
/* harmony export */   createContext: () => (/* binding */ R),
/* harmony export */   createElement: () => (/* binding */ _),
/* harmony export */   createRef: () => (/* binding */ b),
/* harmony export */   h: () => (/* binding */ _),
/* harmony export */   hydrate: () => (/* binding */ K),
/* harmony export */   isValidElement: () => (/* binding */ t),
/* harmony export */   options: () => (/* binding */ l),
/* harmony export */   render: () => (/* binding */ J),
/* harmony export */   toChildArray: () => (/* binding */ L)
/* harmony export */ });
var n,l,u,t,i,r,o,e,f,c,s,a,h,p={},v=[],y=/acit|ex(?:s|g|n|p|$)|rph|grid|ows|mnc|ntw|ine[ch]|zoo|^ord|itera/i,d=Array.isArray;function w(n,l){for(var u in l)n[u]=l[u];return n}function g(n){n&&n.parentNode&&n.parentNode.removeChild(n)}function _(l,u,t){var i,r,o,e={};for(o in u)"key"==o?i=u[o]:"ref"==o?r=u[o]:e[o]=u[o];if(arguments.length>2&&(e.children=arguments.length>3?n.call(arguments,2):t),"function"==typeof l&&null!=l.defaultProps)for(o in l.defaultProps)void 0===e[o]&&(e[o]=l.defaultProps[o]);return m(l,e,i,r,null)}function m(n,t,i,r,o){var e={type:n,props:t,key:i,ref:r,__k:null,__:null,__b:0,__e:null,__c:null,constructor:void 0,__v:null==o?++u:o,__i:-1,__u:0};return null==o&&null!=l.vnode&&l.vnode(e),e}function b(){return{current:null}}function k(n){return n.children}function x(n,l){this.props=n,this.context=l}function S(n,l){if(null==l)return n.__?S(n.__,n.__i+1):null;for(var u;l<n.__k.length;l++)if(null!=(u=n.__k[l])&&null!=u.__e)return u.__e;return"function"==typeof n.type?S(n):null}function C(n){if(n.__P&&n.__d){var u=n.__v,t=u.__e,i=[],r=[],o=w({},u);o.__v=u.__v+1,l.vnode&&l.vnode(o),z(n.__P,o,u,n.__n,n.__P.namespaceURI,32&u.__u?[t]:null,i,null==t?S(u):t,!!(32&u.__u),r),o.__v=u.__v,o.__.__k[o.__i]=o,V(i,o,r),u.__e=u.__=null,o.__e!=t&&M(o)}}function M(n){if(null!=(n=n.__)&&null!=n.__c)return n.__e=n.__c.base=null,n.__k.some(function(l){if(null!=l&&null!=l.__e)return n.__e=n.__c.base=l.__e}),M(n)}function $(n){(!n.__d&&(n.__d=!0)&&i.push(n)&&!I.__r++||r!=l.debounceRendering)&&((r=l.debounceRendering)||o)(I)}function I(){try{for(var n,l=1;i.length;)i.length>l&&i.sort(e),n=i.shift(),l=i.length,C(n)}finally{i.length=I.__r=0}}function P(n,l,u,t,i,r,o,e,f,c,s){var a,h,y,d,w,g,_,m=t&&t.__k||v,b=l.length;for(f=A(u,l,m,f,b),a=0;a<b;a++)null!=(y=u.__k[a])&&(h=-1!=y.__i&&m[y.__i]||p,y.__i=a,g=z(n,y,h,i,r,o,e,f,c,s),d=y.__e,y.ref&&h.ref!=y.ref&&(h.ref&&D(h.ref,null,y),s.push(y.ref,y.__c||d,y)),null==w&&null!=d&&(w=d),(_=!!(4&y.__u))||h.__k===y.__k?f=H(y,f,n,_):"function"==typeof y.type&&void 0!==g?f=g:d&&(f=d.nextSibling),y.__u&=-7);return u.__e=w,f}function A(n,l,u,t,i){var r,o,e,f,c,s=u.length,a=s,h=0;for(n.__k=new Array(i),r=0;r<i;r++)null!=(o=l[r])&&"boolean"!=typeof o&&"function"!=typeof o?("string"==typeof o||"number"==typeof o||"bigint"==typeof o||o.constructor==String?o=n.__k[r]=m(null,o,null,null,null):d(o)?o=n.__k[r]=m(k,{children:o},null,null,null):void 0===o.constructor&&o.__b>0?o=n.__k[r]=m(o.type,o.props,o.key,o.ref?o.ref:null,o.__v):n.__k[r]=o,f=r+h,o.__=n,o.__b=n.__b+1,e=null,-1!=(c=o.__i=T(o,u,f,a))&&(a--,(e=u[c])&&(e.__u|=2)),null==e||null==e.__v?(-1==c&&(i>s?h--:i<s&&h++),"function"!=typeof o.type&&(o.__u|=4)):c!=f&&(c==f-1?h--:c==f+1?h++:(c>f?h--:h++,o.__u|=4))):n.__k[r]=null;if(a)for(r=0;r<s;r++)null!=(e=u[r])&&0==(2&e.__u)&&(e.__e==t&&(t=S(e)),E(e,e));return t}function H(n,l,u,t){var i,r;if("function"==typeof n.type){for(i=n.__k,r=0;i&&r<i.length;r++)i[r]&&(i[r].__=n,l=H(i[r],l,u,t));return l}n.__e!=l&&(t&&(l&&n.type&&!l.parentNode&&(l=S(n)),u.insertBefore(n.__e,l||null)),l=n.__e);do{l=l&&l.nextSibling}while(null!=l&&8==l.nodeType);return l}function L(n,l){return l=l||[],null==n||"boolean"==typeof n||(d(n)?n.some(function(n){L(n,l)}):l.push(n)),l}function T(n,l,u,t){var i,r,o,e=n.key,f=n.type,c=l[u],s=null!=c&&0==(2&c.__u);if(null===c&&null==e||s&&e==c.key&&f==c.type)return u;if(t>(s?1:0))for(i=u-1,r=u+1;i>=0||r<l.length;)if(null!=(c=l[o=i>=0?i--:r++])&&0==(2&c.__u)&&e==c.key&&f==c.type)return o;return-1}function j(n,l,u){"-"==l[0]?n.setProperty(l,null==u?"":u):n[l]=null==u?"":"number"!=typeof u||y.test(l)?u:u+"px"}function F(n,l,u,t,i){var r,o;n:if("style"==l)if("string"==typeof u)n.style.cssText=u;else{if("string"==typeof t&&(n.style.cssText=t=""),t)for(l in t)u&&l in u||j(n.style,l,"");if(u)for(l in u)t&&u[l]==t[l]||j(n.style,l,u[l])}else if("o"==l[0]&&"n"==l[1])r=l!=(l=l.replace(f,"$1")),o=l.toLowerCase(),l=o in n||"onFocusOut"==l||"onFocusIn"==l?o.slice(2):l.slice(2),n.l||(n.l={}),n.l[l+r]=u,u?t?u.u=t.u:(u.u=c,n.addEventListener(l,r?a:s,r)):n.removeEventListener(l,r?a:s,r);else{if("http://www.w3.org/2000/svg"==i)l=l.replace(/xlink(H|:h)/,"h").replace(/sName$/,"s");else if("width"!=l&&"height"!=l&&"href"!=l&&"list"!=l&&"form"!=l&&"tabIndex"!=l&&"download"!=l&&"rowSpan"!=l&&"colSpan"!=l&&"role"!=l&&"popover"!=l&&l in n)try{n[l]=null==u?"":u;break n}catch(n){}"function"==typeof u||(null==u||!1===u&&"-"!=l[4]?n.removeAttribute(l):n.setAttribute(l,"popover"==l&&1==u?"":u))}}function O(n){return function(u){if(this.l){var t=this.l[u.type+n];if(null==u.t)u.t=c++;else if(u.t<t.u)return;return t(l.event?l.event(u):u)}}}function z(n,u,t,i,r,o,e,f,c,s){var a,h,p,y,_,m,b,S,C,M,$,I,A,H,L,T=u.type;if(void 0!==u.constructor)return null;128&t.__u&&(c=!!(32&t.__u),o=[f=u.__e=t.__e]),(a=l.__b)&&a(u);n:if("function"==typeof T)try{if(S=u.props,C=T.prototype&&T.prototype.render,M=(a=T.contextType)&&i[a.__c],$=a?M?M.props.value:a.__:i,t.__c?b=(h=u.__c=t.__c).__=h.__E:(C?u.__c=h=new T(S,$):(u.__c=h=new x(S,$),h.constructor=T,h.render=G),M&&M.sub(h),h.state||(h.state={}),h.__n=i,p=h.__d=!0,h.__h=[],h._sb=[]),C&&null==h.__s&&(h.__s=h.state),C&&null!=T.getDerivedStateFromProps&&(h.__s==h.state&&(h.__s=w({},h.__s)),w(h.__s,T.getDerivedStateFromProps(S,h.__s))),y=h.props,_=h.state,h.__v=u,p)C&&null==T.getDerivedStateFromProps&&null!=h.componentWillMount&&h.componentWillMount(),C&&null!=h.componentDidMount&&h.__h.push(h.componentDidMount);else{if(C&&null==T.getDerivedStateFromProps&&S!==y&&null!=h.componentWillReceiveProps&&h.componentWillReceiveProps(S,$),u.__v==t.__v||!h.__e&&null!=h.shouldComponentUpdate&&!1===h.shouldComponentUpdate(S,h.__s,$)){u.__v!=t.__v&&(h.props=S,h.state=h.__s,h.__d=!1),u.__e=t.__e,u.__k=t.__k,u.__k.some(function(n){n&&(n.__=u)}),v.push.apply(h.__h,h._sb),h._sb=[],h.__h.length&&e.push(h);break n}null!=h.componentWillUpdate&&h.componentWillUpdate(S,h.__s,$),C&&null!=h.componentDidUpdate&&h.__h.push(function(){h.componentDidUpdate(y,_,m)})}if(h.context=$,h.props=S,h.__P=n,h.__e=!1,I=l.__r,A=0,C)h.state=h.__s,h.__d=!1,I&&I(u),a=h.render(h.props,h.state,h.context),v.push.apply(h.__h,h._sb),h._sb=[];else do{h.__d=!1,I&&I(u),a=h.render(h.props,h.state,h.context),h.state=h.__s}while(h.__d&&++A<25);h.state=h.__s,null!=h.getChildContext&&(i=w(w({},i),h.getChildContext())),C&&!p&&null!=h.getSnapshotBeforeUpdate&&(m=h.getSnapshotBeforeUpdate(y,_)),H=null!=a&&a.type===k&&null==a.key?q(a.props.children):a,f=P(n,d(H)?H:[H],u,t,i,r,o,e,f,c,s),h.base=u.__e,u.__u&=-161,h.__h.length&&e.push(h),b&&(h.__E=h.__=null)}catch(n){if(u.__v=null,c||null!=o)if(n.then){for(u.__u|=c?160:128;f&&8==f.nodeType&&f.nextSibling;)f=f.nextSibling;o[o.indexOf(f)]=null,u.__e=f}else{for(L=o.length;L--;)g(o[L]);N(u)}else u.__e=t.__e,u.__k=t.__k,n.then||N(u);l.__e(n,u,t)}else null==o&&u.__v==t.__v?(u.__k=t.__k,u.__e=t.__e):f=u.__e=B(t.__e,u,t,i,r,o,e,c,s);return(a=l.diffed)&&a(u),128&u.__u?void 0:f}function N(n){n&&(n.__c&&(n.__c.__e=!0),n.__k&&n.__k.some(N))}function V(n,u,t){for(var i=0;i<t.length;i++)D(t[i],t[++i],t[++i]);l.__c&&l.__c(u,n),n.some(function(u){try{n=u.__h,u.__h=[],n.some(function(n){n.call(u)})}catch(n){l.__e(n,u.__v)}})}function q(n){return"object"!=typeof n||null==n||n.__b>0?n:d(n)?n.map(q):w({},n)}function B(u,t,i,r,o,e,f,c,s){var a,h,v,y,w,_,m,b=i.props||p,k=t.props,x=t.type;if("svg"==x?o="http://www.w3.org/2000/svg":"math"==x?o="http://www.w3.org/1998/Math/MathML":o||(o="http://www.w3.org/1999/xhtml"),null!=e)for(a=0;a<e.length;a++)if((w=e[a])&&"setAttribute"in w==!!x&&(x?w.localName==x:3==w.nodeType)){u=w,e[a]=null;break}if(null==u){if(null==x)return document.createTextNode(k);u=document.createElementNS(o,x,k.is&&k),c&&(l.__m&&l.__m(t,e),c=!1),e=null}if(null==x)b===k||c&&u.data==k||(u.data=k);else{if(e=e&&n.call(u.childNodes),!c&&null!=e)for(b={},a=0;a<u.attributes.length;a++)b[(w=u.attributes[a]).name]=w.value;for(a in b)w=b[a],"dangerouslySetInnerHTML"==a?v=w:"children"==a||a in k||"value"==a&&"defaultValue"in k||"checked"==a&&"defaultChecked"in k||F(u,a,null,w,o);for(a in k)w=k[a],"children"==a?y=w:"dangerouslySetInnerHTML"==a?h=w:"value"==a?_=w:"checked"==a?m=w:c&&"function"!=typeof w||b[a]===w||F(u,a,w,b[a],o);if(h)c||v&&(h.__html==v.__html||h.__html==u.innerHTML)||(u.innerHTML=h.__html),t.__k=[];else if(v&&(u.innerHTML=""),P("template"==t.type?u.content:u,d(y)?y:[y],t,i,r,"foreignObject"==x?"http://www.w3.org/1999/xhtml":o,e,f,e?e[0]:i.__k&&S(i,0),c,s),null!=e)for(a=e.length;a--;)g(e[a]);c||(a="value","progress"==x&&null==_?u.removeAttribute("value"):null!=_&&(_!==u[a]||"progress"==x&&!_||"option"==x&&_!=b[a])&&F(u,a,_,b[a],o),a="checked",null!=m&&m!=u[a]&&F(u,a,m,b[a],o))}return u}function D(n,u,t){try{if("function"==typeof n){var i="function"==typeof n.__u;i&&n.__u(),i&&null==u||(n.__u=n(u))}else n.current=u}catch(n){l.__e(n,t)}}function E(n,u,t){var i,r;if(l.unmount&&l.unmount(n),(i=n.ref)&&(i.current&&i.current!=n.__e||D(i,null,u)),null!=(i=n.__c)){if(i.componentWillUnmount)try{i.componentWillUnmount()}catch(n){l.__e(n,u)}i.base=i.__P=null}if(i=n.__k)for(r=0;r<i.length;r++)i[r]&&E(i[r],u,t||"function"!=typeof n.type);t||g(n.__e),n.__c=n.__=n.__e=void 0}function G(n,l,u){return this.constructor(n,u)}function J(u,t,i){var r,o,e,f;t==document&&(t=document.documentElement),l.__&&l.__(u,t),o=(r="function"==typeof i)?null:i&&i.__k||t.__k,e=[],f=[],z(t,u=(!r&&i||t).__k=_(k,null,[u]),o||p,p,t.namespaceURI,!r&&i?[i]:o?null:t.firstChild?n.call(t.childNodes):null,e,!r&&i?i:o?o.__e:t.firstChild,r,f),V(e,u,f)}function K(n,l){J(n,l,K)}function Q(l,u,t){var i,r,o,e,f=w({},l.props);for(o in l.type&&l.type.defaultProps&&(e=l.type.defaultProps),u)"key"==o?i=u[o]:"ref"==o?r=u[o]:f[o]=void 0===u[o]&&null!=e?e[o]:u[o];return arguments.length>2&&(f.children=arguments.length>3?n.call(arguments,2):t),m(l.type,f,i||l.key,r||l.ref,null)}function R(n){function l(n){var u,t;return this.getChildContext||(u=new Set,(t={})[l.__c]=this,this.getChildContext=function(){return t},this.componentWillUnmount=function(){u=null},this.shouldComponentUpdate=function(n){this.props.value!=n.value&&u.forEach(function(n){n.__e=!0,$(n)})},this.sub=function(n){u.add(n);var l=n.componentWillUnmount;n.componentWillUnmount=function(){u&&u.delete(n),l&&l.call(n)}}),n.children}return l.__c="__cC"+h++,l.__=n,l.Provider=l.__l=(l.Consumer=function(n,l){return n.children(l)}).contextType=l,l}n=v.slice,l={__e:function(n,l,u,t){for(var i,r,o;l=l.__;)if((i=l.__c)&&!i.__)try{if((r=i.constructor)&&null!=r.getDerivedStateFromError&&(i.setState(r.getDerivedStateFromError(n)),o=i.__d),null!=i.componentDidCatch&&(i.componentDidCatch(n,t||{}),o=i.__d),o)return i.__E=i}catch(l){n=l}throw n}},u=0,t=function(n){return null!=n&&void 0===n.constructor},x.prototype.setState=function(n,l){var u;u=null!=this.__s&&this.__s!=this.state?this.__s:this.__s=w({},this.state),"function"==typeof n&&(n=n(w({},u),this.props)),n&&w(u,n),null!=n&&this.__v&&(l&&this._sb.push(l),$(this))},x.prototype.forceUpdate=function(n){this.__v&&(this.__e=!0,n&&this.__h.push(n),$(this))},x.prototype.render=k,i=[],o="function"==typeof Promise?Promise.prototype.then.bind(Promise.resolve()):setTimeout,e=function(n,l){return n.__v.__b-l.__v.__b},I.__r=0,f=/(PointerCapture)$|Capture$/i,c=0,s=O(!1),a=O(!0),h=0;
//# sourceMappingURL=preact.module.js.map


/***/ },

/***/ "./node_modules/preact/hooks/dist/hooks.module.js"
/*!********************************************************!*\
  !*** ./node_modules/preact/hooks/dist/hooks.module.js ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useCallback: () => (/* binding */ q),
/* harmony export */   useContext: () => (/* binding */ x),
/* harmony export */   useDebugValue: () => (/* binding */ P),
/* harmony export */   useEffect: () => (/* binding */ y),
/* harmony export */   useErrorBoundary: () => (/* binding */ b),
/* harmony export */   useId: () => (/* binding */ g),
/* harmony export */   useImperativeHandle: () => (/* binding */ F),
/* harmony export */   useLayoutEffect: () => (/* binding */ _),
/* harmony export */   useMemo: () => (/* binding */ T),
/* harmony export */   useReducer: () => (/* binding */ h),
/* harmony export */   useRef: () => (/* binding */ A),
/* harmony export */   useState: () => (/* binding */ d)
/* harmony export */ });
/* harmony import */ var preact__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! preact */ "./node_modules/preact/dist/preact.module.js");
var t,r,u,i,o=0,f=[],c=preact__WEBPACK_IMPORTED_MODULE_0__.options,e=c.__b,a=c.__r,v=c.diffed,l=c.__c,m=c.unmount,s=c.__;function p(n,t){c.__h&&c.__h(r,n,o||t),o=0;var u=r.__H||(r.__H={__:[],__h:[]});return n>=u.__.length&&u.__.push({}),u.__[n]}function d(n){return o=1,h(D,n)}function h(n,u,i){var o=p(t++,2);if(o.t=n,!o.__c&&(o.__=[i?i(u):D(void 0,u),function(n){var t=o.__N?o.__N[0]:o.__[0],r=o.t(t,n);t!==r&&(o.__N=[r,o.__[1]],o.__c.setState({}))}],o.__c=r,!r.__f)){var f=function(n,t,r){if(!o.__c.__H)return!0;var u=o.__c.__H.__.filter(function(n){return n.__c});if(u.every(function(n){return!n.__N}))return!c||c.call(this,n,t,r);var i=o.__c.props!==n;return u.some(function(n){if(n.__N){var t=n.__[0];n.__=n.__N,n.__N=void 0,t!==n.__[0]&&(i=!0)}}),c&&c.call(this,n,t,r)||i};r.__f=!0;var c=r.shouldComponentUpdate,e=r.componentWillUpdate;r.componentWillUpdate=function(n,t,r){if(this.__e){var u=c;c=void 0,f(n,t,r),c=u}e&&e.call(this,n,t,r)},r.shouldComponentUpdate=f}return o.__N||o.__}function y(n,u){var i=p(t++,3);!c.__s&&C(i.__H,u)&&(i.__=n,i.u=u,r.__H.__h.push(i))}function _(n,u){var i=p(t++,4);!c.__s&&C(i.__H,u)&&(i.__=n,i.u=u,r.__h.push(i))}function A(n){return o=5,T(function(){return{current:n}},[])}function F(n,t,r){o=6,_(function(){if("function"==typeof n){var r=n(t());return function(){n(null),r&&"function"==typeof r&&r()}}if(n)return n.current=t(),function(){return n.current=null}},null==r?r:r.concat(n))}function T(n,r){var u=p(t++,7);return C(u.__H,r)&&(u.__=n(),u.__H=r,u.__h=n),u.__}function q(n,t){return o=8,T(function(){return n},t)}function x(n){var u=r.context[n.__c],i=p(t++,9);return i.c=n,u?(null==i.__&&(i.__=!0,u.sub(r)),u.props.value):n.__}function P(n,t){c.useDebugValue&&c.useDebugValue(t?t(n):n)}function b(n){var u=p(t++,10),i=d();return u.__=n,r.componentDidCatch||(r.componentDidCatch=function(n,t){u.__&&u.__(n,t),i[1](n)}),[i[0],function(){i[1](void 0)}]}function g(){var n=p(t++,11);if(!n.__){for(var u=r.__v;null!==u&&!u.__m&&null!==u.__;)u=u.__;var i=u.__m||(u.__m=[0,0]);n.__="P"+i[0]+"-"+i[1]++}return n.__}function j(){for(var n;n=f.shift();){var t=n.__H;if(n.__P&&t)try{t.__h.some(z),t.__h.some(B),t.__h=[]}catch(r){t.__h=[],c.__e(r,n.__v)}}}c.__b=function(n){r=null,e&&e(n)},c.__=function(n,t){n&&t.__k&&t.__k.__m&&(n.__m=t.__k.__m),s&&s(n,t)},c.__r=function(n){a&&a(n),t=0;var i=(r=n.__c).__H;i&&(u===r?(i.__h=[],r.__h=[],i.__.some(function(n){n.__N&&(n.__=n.__N),n.u=n.__N=void 0})):(i.__h.some(z),i.__h.some(B),i.__h=[],t=0)),u=r},c.diffed=function(n){v&&v(n);var t=n.__c;t&&t.__H&&(t.__H.__h.length&&(1!==f.push(t)&&i===c.requestAnimationFrame||((i=c.requestAnimationFrame)||w)(j)),t.__H.__.some(function(n){n.u&&(n.__H=n.u),n.u=void 0})),u=r=null},c.__c=function(n,t){t.some(function(n){try{n.__h.some(z),n.__h=n.__h.filter(function(n){return!n.__||B(n)})}catch(r){t.some(function(n){n.__h&&(n.__h=[])}),t=[],c.__e(r,n.__v)}}),l&&l(n,t)},c.unmount=function(n){m&&m(n);var t,r=n.__c;r&&r.__H&&(r.__H.__.some(function(n){try{z(n)}catch(n){t=n}}),r.__H=void 0,t&&c.__e(t,r.__v))};var k="function"==typeof requestAnimationFrame;function w(n){var t,r=function(){clearTimeout(u),k&&cancelAnimationFrame(t),setTimeout(n)},u=setTimeout(r,35);k&&(t=requestAnimationFrame(r))}function z(n){var t=r,u=n.__c;"function"==typeof u&&(n.__c=void 0,u()),r=t}function B(n){var t=r;n.__c=n.__(),r=t}function C(n,t){return!n||n.length!==t.length||t.some(function(t,r){return t!==n[r]})}function D(n,t){return"function"==typeof t?t(n):t}
//# sourceMappingURL=hooks.module.js.map


/***/ },

/***/ "./node_modules/preact/jsx-runtime/dist/jsxRuntime.module.js"
/*!*******************************************************************!*\
  !*** ./node_modules/preact/jsx-runtime/dist/jsxRuntime.module.js ***!
  \*******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Fragment: () => (/* reexport safe */ preact__WEBPACK_IMPORTED_MODULE_0__.Fragment),
/* harmony export */   jsx: () => (/* binding */ u),
/* harmony export */   jsxAttr: () => (/* binding */ l),
/* harmony export */   jsxDEV: () => (/* binding */ u),
/* harmony export */   jsxEscape: () => (/* binding */ s),
/* harmony export */   jsxTemplate: () => (/* binding */ a),
/* harmony export */   jsxs: () => (/* binding */ u)
/* harmony export */ });
/* harmony import */ var preact__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! preact */ "./node_modules/preact/dist/preact.module.js");
var t=/["&<]/;function n(r){if(0===r.length||!1===t.test(r))return r;for(var e=0,n=0,o="",f="";n<r.length;n++){switch(r.charCodeAt(n)){case 34:f="&quot;";break;case 38:f="&amp;";break;case 60:f="&lt;";break;default:continue}n!==e&&(o+=r.slice(e,n)),o+=f,e=n+1}return n!==e&&(o+=r.slice(e,n)),o}var o=/acit|ex(?:s|g|n|p|$)|rph|grid|ows|mnc|ntw|ine[ch]|zoo|^ord|itera/i,f=0,i=Array.isArray;function u(e,t,n,o,i,u){t||(t={});var a,c,p=t;if("ref"in p)for(c in p={},t)"ref"==c?a=t[c]:p[c]=t[c];var l={type:e,props:p,key:n,ref:a,__k:null,__:null,__b:0,__e:null,__c:null,constructor:void 0,__v:--f,__i:-1,__u:0,__source:i,__self:u};if("function"==typeof e&&(a=e.defaultProps))for(c in a)void 0===p[c]&&(p[c]=a[c]);return preact__WEBPACK_IMPORTED_MODULE_0__.options.vnode&&preact__WEBPACK_IMPORTED_MODULE_0__.options.vnode(l),l}function a(r){var t=u(preact__WEBPACK_IMPORTED_MODULE_0__.Fragment,{tpl:r,exprs:[].slice.call(arguments,1)});return t.key=t.__v,t}var c={},p=/[A-Z]/g;function l(e,t){if(preact__WEBPACK_IMPORTED_MODULE_0__.options.attr){var f=preact__WEBPACK_IMPORTED_MODULE_0__.options.attr(e,t);if("string"==typeof f)return f}if(t=function(r){return null!==r&&"object"==typeof r&&"function"==typeof r.valueOf?r.valueOf():r}(t),"ref"===e||"key"===e)return"";if("style"===e&&"object"==typeof t){var i="";for(var u in t){var a=t[u];if(null!=a&&""!==a){var l="-"==u[0]?u:c[u]||(c[u]=u.replace(p,"-$&").toLowerCase()),s=";";"number"!=typeof a||l.startsWith("--")||o.test(l)||(s="px;"),i=i+l+":"+a+s}}return e+'="'+n(i)+'"'}return null==t||!1===t||"function"==typeof t||"object"==typeof t?"":!0===t?e:e+'="'+n(""+t)+'"'}function s(r){if(null==r||"boolean"==typeof r||"function"==typeof r)return null;if("object"==typeof r){if(void 0===r.constructor)return r;if(i(r)){for(var e=0;e<r.length;e++)r[e]=s(r[e]);return r}}return n(""+r)}
//# sourceMappingURL=jsxRuntime.module.js.map


/***/ },

/***/ "./node_modules/wildcard/index.js"
/*!****************************************!*\
  !*** ./node_modules/wildcard/index.js ***!
  \****************************************/
(module) {

"use strict";
/* jshint node: true */


/**
  # wildcard

  Very simple wildcard matching, which is designed to provide the same
  functionality that is found in the
  [eve](https://github.com/adobe-webplatform/eve) eventing library.

  ## Usage

  It works with strings:

  <<< examples/strings.js

  Arrays:

  <<< examples/arrays.js

  Objects (matching against keys):

  <<< examples/objects.js

  While the library works in Node, if you are are looking for file-based
  wildcard matching then you should have a look at:

  <https://github.com/isaacs/node-glob>
**/

function WildcardMatcher(text, separator) {
  this.text = text = text || '';
  this.hasWild = ~text.indexOf('*');
  this.separator = separator;
  this.parts = text.split(separator);
}

WildcardMatcher.prototype.match = function(input) {
  var matches = true;
  var parts = this.parts;
  var ii;
  var partsCount = parts.length;
  var testParts;

  if (typeof input == 'string' || input instanceof String) {
    if (!this.hasWild && this.text != input) {
      matches = false;
    } else {
      testParts = (input || '').split(this.separator);
      for (ii = 0; matches && ii < partsCount; ii++) {
        if (parts[ii] === '*')  {
          continue;
        } else if (ii < testParts.length) {
          matches = parts[ii] === testParts[ii];
        } else {
          matches = false;
        }
      }

      // If matches, then return the component parts
      matches = matches && testParts;
    }
  }
  else if (typeof input.splice == 'function') {
    matches = [];

    for (ii = input.length; ii--; ) {
      if (this.match(input[ii])) {
        matches[matches.length] = input[ii];
      }
    }
  }
  else if (typeof input == 'object') {
    matches = {};

    for (var key in input) {
      if (this.match(key)) {
        matches[key] = input[key];
      }
    }
  }

  return matches;
};

module.exports = function(text, test, separator) {
  var matcher = new WildcardMatcher(text, separator || /[\/\.]/);
  if (typeof test != 'undefined') {
    return matcher.match(test);
  }

  return matcher;
};


/***/ },

/***/ "./node_modules/@uppy/core/lib/BasePlugin.js"
/*!***************************************************!*\
  !*** ./node_modules/@uppy/core/lib/BasePlugin.js ***!
  \***************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ BasePlugin)
/* harmony export */ });
/* harmony import */ var _uppy_utils_lib_Translator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @uppy/utils/lib/Translator */ "./node_modules/@uppy/utils/lib/Translator.js");
/**
 * Core plugin logic that all plugins share.
 *
 * BasePlugin does not contain DOM rendering so it can be used for plugins
 * without a user interface.
 *
 * See `Plugin` for the extended version with Preact rendering for interfaces.
 */

class BasePlugin {
    uppy;
    opts;
    id;
    defaultLocale;
    i18n;
    i18nArray;
    type;
    VERSION;
    constructor(uppy, opts) {
        this.uppy = uppy;
        this.opts = opts ?? {};
    }
    getPluginState() {
        const { plugins } = this.uppy.getState();
        return (plugins?.[this.id] || {});
    }
    setPluginState(update) {
        const { plugins } = this.uppy.getState();
        this.uppy.setState({
            plugins: {
                ...plugins,
                [this.id]: {
                    ...plugins[this.id],
                    ...update,
                },
            },
        });
    }
    setOptions(newOpts) {
        this.opts = { ...this.opts, ...newOpts };
        this.setPluginState(undefined); // so that UI re-renders with new options
        this.i18nInit();
    }
    i18nInit() {
        const translator = new _uppy_utils_lib_Translator__WEBPACK_IMPORTED_MODULE_0__["default"]([
            this.defaultLocale,
            this.uppy.locale,
            this.opts.locale,
        ]);
        this.i18n = translator.translate.bind(translator);
        this.i18nArray = translator.translateArray.bind(translator);
        this.setPluginState(undefined); // so that UI re-renders and we see the updated locale
    }
    /**
     * Extendable methods
     * ==================
     * These methods are here to serve as an overview of the extendable methods as well as
     * making them not conditional in use, such as `if (this.afterUpdate)`.
     */
    addTarget(plugin) {
        throw new Error("Extend the addTarget method to add your plugin to another plugin's target");
    }
    install() { }
    uninstall() { }
    update(state) { }
    // Called after every state update, after everything's mounted. Debounced.
    afterUpdate() { }
}


/***/ },

/***/ "./node_modules/@uppy/core/lib/EventManager.js"
/*!*****************************************************!*\
  !*** ./node_modules/@uppy/core/lib/EventManager.js ***!
  \*****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ EventManager)
/* harmony export */ });
/**
 * Create a wrapper around an event emitter with a `remove` method to remove
 * all events that were added using the wrapped emitter.
 */
class EventManager {
    #uppy;
    #events = [];
    constructor(uppy) {
        this.#uppy = uppy;
    }
    on(event, fn) {
        this.#events.push([event, fn]);
        return this.#uppy.on(event, fn);
    }
    remove() {
        for (const [event, fn] of this.#events.splice(0)) {
            this.#uppy.off(event, fn);
        }
    }
    onFilePause(fileID, cb) {
        this.on('upload-pause', (file, isPaused) => {
            if (fileID === file?.id) {
                cb(isPaused);
            }
        });
    }
    onFileRemove(fileID, cb) {
        this.on('file-removed', (file) => {
            if (fileID === file.id)
                cb(file.id);
        });
    }
    onPause(fileID, cb) {
        this.on('upload-pause', (file, isPaused) => {
            if (fileID === file?.id) {
                // const isPaused = this.#uppy.pauseResume(fileID)
                cb(isPaused);
            }
        });
    }
    onRetry(fileID, cb) {
        this.on('upload-retry', (file) => {
            if (fileID === file?.id) {
                cb();
            }
        });
    }
    onRetryAll(fileID, cb) {
        this.on('retry-all', () => {
            if (!this.#uppy.getFile(fileID))
                return;
            cb();
        });
    }
    onPauseAll(fileID, cb) {
        this.on('pause-all', () => {
            if (!this.#uppy.getFile(fileID))
                return;
            cb();
        });
    }
    onCancelAll(fileID, eventHandler) {
        this.on('cancel-all', (...args) => {
            if (!this.#uppy.getFile(fileID))
                return;
            eventHandler(...args);
        });
    }
    onResumeAll(fileID, cb) {
        this.on('resume-all', () => {
            if (!this.#uppy.getFile(fileID))
                return;
            cb();
        });
    }
}


/***/ },

/***/ "./node_modules/@uppy/core/lib/Restricter.js"
/*!***************************************************!*\
  !*** ./node_modules/@uppy/core/lib/Restricter.js ***!
  \***************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Restricter: () => (/* binding */ Restricter),
/* harmony export */   RestrictionError: () => (/* binding */ RestrictionError),
/* harmony export */   defaultOptions: () => (/* binding */ defaultOptions)
/* harmony export */ });
/* harmony import */ var _transloadit_prettier_bytes__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @transloadit/prettier-bytes */ "./node_modules/@transloadit/prettier-bytes/dist/prettierBytes.js");
/* harmony import */ var mime_match__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! mime-match */ "./node_modules/mime-match/index.js");

// @ts-ignore untyped

const defaultOptions = {
    maxFileSize: null,
    minFileSize: null,
    maxTotalFileSize: null,
    maxNumberOfFiles: null,
    minNumberOfFiles: null,
    allowedFileTypes: null,
    requiredMetaFields: [],
};
class RestrictionError extends Error {
    isUserFacing;
    file;
    constructor(message, opts) {
        super(message);
        this.isUserFacing = opts?.isUserFacing ?? true;
        if (opts?.file) {
            this.file = opts.file; // only some restriction errors are related to a particular file
        }
    }
    isRestriction = true;
}
class Restricter {
    getI18n;
    getOpts;
    constructor(getOpts, getI18n) {
        this.getI18n = getI18n;
        this.getOpts = () => {
            const opts = getOpts();
            if (opts.restrictions?.allowedFileTypes != null &&
                !Array.isArray(opts.restrictions.allowedFileTypes)) {
                throw new TypeError('`restrictions.allowedFileTypes` must be an array');
            }
            return opts;
        };
    }
    // Because these operations are slow, we cannot run them for every file (if we are adding multiple files)
    validateAggregateRestrictions(existingFiles, addingFiles) {
        const { maxTotalFileSize, maxNumberOfFiles } = this.getOpts().restrictions;
        if (maxNumberOfFiles) {
            const nonGhostFiles = existingFiles.filter((f) => !f.isGhost);
            if (nonGhostFiles.length + addingFiles.length > maxNumberOfFiles) {
                throw new RestrictionError(`${this.getI18n()('youCanOnlyUploadX', {
                    smart_count: maxNumberOfFiles,
                })}`);
            }
        }
        if (maxTotalFileSize) {
            const totalFilesSize = [...existingFiles, ...addingFiles].reduce((total, f) => total + (f.size ?? 0), 0);
            if (totalFilesSize > maxTotalFileSize) {
                throw new RestrictionError(this.getI18n()('aggregateExceedsSize', {
                    sizeAllowed: _transloadit_prettier_bytes__WEBPACK_IMPORTED_MODULE_0__(maxTotalFileSize),
                    size: _transloadit_prettier_bytes__WEBPACK_IMPORTED_MODULE_0__(totalFilesSize),
                }));
            }
        }
    }
    validateSingleFile(file) {
        const { maxFileSize, minFileSize, allowedFileTypes } = this.getOpts().restrictions;
        if (allowedFileTypes) {
            const isCorrectFileType = allowedFileTypes.some((type) => {
                // check if this is a mime-type
                if (type.includes('/')) {
                    if (!file.type)
                        return false;
                    return mime_match__WEBPACK_IMPORTED_MODULE_1__(file.type.replace(/;.*?$/, ''), type);
                }
                // otherwise this is likely an extension
                if (type[0] === '.' && file.extension) {
                    return file.extension.toLowerCase() === type.slice(1).toLowerCase();
                }
                return false;
            });
            if (!isCorrectFileType) {
                const allowedFileTypesString = allowedFileTypes.join(', ');
                throw new RestrictionError(this.getI18n()('youCanOnlyUploadFileTypes', {
                    types: allowedFileTypesString,
                }), { file });
            }
        }
        // We can't check maxFileSize if the size is unknown.
        if (maxFileSize && file.size != null && file.size > maxFileSize) {
            throw new RestrictionError(this.getI18n()('exceedsSize', {
                size: _transloadit_prettier_bytes__WEBPACK_IMPORTED_MODULE_0__(maxFileSize),
                file: file.name ?? this.getI18n()('unnamed'),
            }), { file });
        }
        // We can't check minFileSize if the size is unknown.
        if (minFileSize && file.size != null && file.size < minFileSize) {
            throw new RestrictionError(this.getI18n()('inferiorSize', {
                size: _transloadit_prettier_bytes__WEBPACK_IMPORTED_MODULE_0__(minFileSize),
            }), { file });
        }
    }
    validate(existingFiles, addingFiles) {
        addingFiles.forEach((addingFile) => {
            this.validateSingleFile(addingFile);
        });
        this.validateAggregateRestrictions(existingFiles, addingFiles);
    }
    validateMinNumberOfFiles(files) {
        const { minNumberOfFiles } = this.getOpts().restrictions;
        if (minNumberOfFiles && Object.keys(files).length < minNumberOfFiles) {
            throw new RestrictionError(this.getI18n()('youHaveToAtLeastSelectX', {
                smart_count: minNumberOfFiles,
            }));
        }
    }
    getMissingRequiredMetaFields(file) {
        const error = new RestrictionError(this.getI18n()('missingRequiredMetaFieldOnFile', {
            fileName: file.name ?? this.getI18n()('unnamed'),
        }));
        const { requiredMetaFields } = this.getOpts().restrictions;
        const missingFields = [];
        for (const field of requiredMetaFields) {
            if (!Object.hasOwn(file.meta, field) || file.meta[field] === '') {
                missingFields.push(field);
            }
        }
        return { missingFields, error };
    }
}



/***/ },

/***/ "./node_modules/@uppy/core/lib/UIPlugin.js"
/*!*************************************************!*\
  !*** ./node_modules/@uppy/core/lib/UIPlugin.js ***!
  \*************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _uppy_utils_lib_findDOMElement__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @uppy/utils/lib/findDOMElement */ "./node_modules/@uppy/utils/lib/findDOMElement.js");
/* harmony import */ var _uppy_utils_lib_getTextDirection__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @uppy/utils/lib/getTextDirection */ "./node_modules/@uppy/utils/lib/getTextDirection.js");
/* harmony import */ var preact_compat__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! preact/compat */ "./node_modules/preact/compat/dist/compat.module.js");
/* harmony import */ var _BasePlugin_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./BasePlugin.js */ "./node_modules/@uppy/core/lib/BasePlugin.js");




/**
 * Defer a frequent call to the microtask queue.
 */
function debounce(fn) {
    let calling = null;
    let latestArgs;
    return (...args) => {
        latestArgs = args;
        if (!calling) {
            calling = Promise.resolve().then(() => {
                calling = null;
                // At this point `args` may be different from the most
                // recent state, if multiple calls happened since this task
                // was queued. So we use the `latestArgs`, which definitely
                // is the most recent call.
                return fn(...latestArgs);
            });
        }
        return calling;
    };
}
/**
 * UIPlugin is the extended version of BasePlugin to incorporate rendering with Preact.
 * Use this for plugins that need a user interface.
 *
 * For plugins without an user interface, see BasePlugin.
 */
class UIPlugin extends _BasePlugin_js__WEBPACK_IMPORTED_MODULE_3__["default"] {
    #updateUI;
    isTargetDOMEl;
    el;
    parent;
    title;
    getTargetPlugin(target) {
        let targetPlugin;
        if (typeof target?.addTarget === 'function') {
            // Targeting a plugin *instance*
            targetPlugin = target;
            if (!(targetPlugin instanceof UIPlugin)) {
                console.warn(new Error('The provided plugin is not an instance of UIPlugin. This is an indication of a bug with the way Uppy is bundled.', { cause: { targetPlugin, UIPlugin } }));
            }
        }
        else if (typeof target === 'function') {
            // Targeting a plugin type
            const Target = target;
            // Find the target plugin instance.
            this.uppy.iteratePlugins((p) => {
                if (p instanceof Target) {
                    targetPlugin = p;
                }
            });
        }
        return targetPlugin;
    }
    /**
     * Check if supplied `target` is a DOM element or an `object`.
     * If it’s an object — target is a plugin, and we search `plugins`
     * for a plugin with same name and return its target.
     */
    mount(target, plugin) {
        const callerPluginName = plugin.id;
        const targetElement = (0,_uppy_utils_lib_findDOMElement__WEBPACK_IMPORTED_MODULE_0__["default"])(target);
        if (targetElement) {
            this.isTargetDOMEl = true;
            // When target is <body> with a single <div> element,
            // Preact thinks it’s the Uppy root element in there when doing a diff,
            // and destroys it. So we are creating a fragment (could be empty div)
            const uppyRootElement = document.createElement('div');
            uppyRootElement.classList.add('uppy-Root');
            // API for plugins that require a synchronous rerender.
            this.#updateUI = debounce((state) => {
                // plugin could be removed, but this.rerender is debounced below,
                // so it could still be called even after uppy.removePlugin or uppy.destroy
                // hence the check
                if (!this.uppy.getPlugin(this.id))
                    return;
                (0,preact_compat__WEBPACK_IMPORTED_MODULE_2__.render)(this.render(state, uppyRootElement), uppyRootElement);
                this.afterUpdate();
            });
            this.uppy.log(`Installing ${callerPluginName} to a DOM element '${target}'`);
            if (this.opts.replaceTargetContent) {
                // Doing render(h(null), targetElement), which should have been
                // a better way, since because the component might need to do additional cleanup when it is removed,
                // stopped working — Preact just adds null into target, not replacing
                targetElement.innerHTML = '';
            }
            (0,preact_compat__WEBPACK_IMPORTED_MODULE_2__.render)(this.render(this.uppy.getState(), uppyRootElement), uppyRootElement);
            this.el = uppyRootElement;
            targetElement.appendChild(uppyRootElement);
            // Set the text direction if the page has not defined one.
            uppyRootElement.dir =
                this.opts.direction || (0,_uppy_utils_lib_getTextDirection__WEBPACK_IMPORTED_MODULE_1__["default"])(uppyRootElement) || 'ltr';
            this.onMount();
            return this.el;
        }
        const targetPlugin = this.getTargetPlugin(target);
        if (targetPlugin) {
            this.uppy.log(`Installing ${callerPluginName} to ${targetPlugin.id}`);
            this.parent = targetPlugin;
            this.el = targetPlugin.addTarget(plugin);
            this.onMount();
            return this.el;
        }
        this.uppy.log(`Not installing ${callerPluginName}`);
        let message = `Invalid target option given to ${callerPluginName}.`;
        if (typeof target === 'function') {
            message +=
                ' The given target is not a Plugin class. ' +
                    "Please check that you're not specifying a React Component instead of a plugin. " +
                    'If you are using @uppy/* packages directly, make sure you have only 1 version of @uppy/core installed: ' +
                    'run `npm ls @uppy/core` on the command line and verify that all the versions match and are deduped correctly.';
        }
        else {
            message +=
                'If you meant to target an HTML element, please make sure that the element exists. ' +
                    'Check that the <script> tag initializing Uppy is right before the closing </body> tag at the end of the page. ' +
                    '(see https://github.com/transloadit/uppy/issues/1042)\n\n' +
                    'If you meant to target a plugin, please confirm that your `import` statements or `require` calls are correct.';
        }
        throw new Error(message);
    }
    /**
     * Called when plugin is mounted, whether in DOM or into another plugin.
     * Needed because sometimes plugins are mounted separately/after `install`,
     * so this.el and this.parent might not be available in `install`.
     * This is the case with @uppy/react plugins, for example.
     */
    render(state, container) {
        throw new Error('Extend the render method to add your plugin to a DOM element');
    }
    update(state) {
        if (this.el != null) {
            this.#updateUI?.(state);
        }
    }
    unmount() {
        if (this.isTargetDOMEl) {
            this.el?.remove();
        }
        this.onUnmount();
    }
    onMount() { }
    onUnmount() { }
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (UIPlugin);


/***/ },

/***/ "./node_modules/@uppy/core/lib/Uppy.js"
/*!*********************************************!*\
  !*** ./node_modules/@uppy/core/lib/Uppy.js ***!
  \*********************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Uppy: () => (/* binding */ Uppy),
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _uppy_store_default__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @uppy/store-default */ "./node_modules/@uppy/store-default/lib/index.js");
/* harmony import */ var _uppy_utils_lib_generateFileID__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @uppy/utils/lib/generateFileID */ "./node_modules/@uppy/utils/lib/generateFileID.js");
/* harmony import */ var _uppy_utils_lib_getFileNameAndExtension__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @uppy/utils/lib/getFileNameAndExtension */ "./node_modules/@uppy/utils/lib/getFileNameAndExtension.js");
/* harmony import */ var _uppy_utils_lib_getFileType__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @uppy/utils/lib/getFileType */ "./node_modules/@uppy/utils/lib/getFileType.js");
/* harmony import */ var _uppy_utils_lib_Translator__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @uppy/utils/lib/Translator */ "./node_modules/@uppy/utils/lib/Translator.js");
/* harmony import */ var lodash_throttle_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! lodash/throttle.js */ "./node_modules/lodash/throttle.js");
/* harmony import */ var namespace_emitter__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! namespace-emitter */ "./node_modules/namespace-emitter/index.js");
/* harmony import */ var nanoid_non_secure__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! nanoid/non-secure */ "./node_modules/nanoid/non-secure/index.js");
/* harmony import */ var _package_json__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../package.json */ "./node_modules/@uppy/core/package.json");
/* harmony import */ var _getFileName_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./getFileName.js */ "./node_modules/@uppy/core/lib/getFileName.js");
/* harmony import */ var _locale_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./locale.js */ "./node_modules/@uppy/core/lib/locale.js");
/* harmony import */ var _loggers_js__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./loggers.js */ "./node_modules/@uppy/core/lib/loggers.js");
/* harmony import */ var _Restricter_js__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./Restricter.js */ "./node_modules/@uppy/core/lib/Restricter.js");
/* harmony import */ var _supportsUploadProgress_js__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./supportsUploadProgress.js */ "./node_modules/@uppy/core/lib/supportsUploadProgress.js");
/* global AggregateError */






// @ts-ignore untyped








const defaultUploadState = {
    totalProgress: 0,
    allowNewUpload: true,
    error: null,
    recoveredState: null,
};
/**
 * Uppy Core module.
 * Manages plugins, state updates, acts as an event bus,
 * adds/removes files and metadata.
 */
class Uppy {
    static VERSION = _package_json__WEBPACK_IMPORTED_MODULE_8__.version;
    #plugins = Object.create(null);
    #restricter;
    #storeUnsubscribe;
    #emitter = namespace_emitter__WEBPACK_IMPORTED_MODULE_6__();
    #preProcessors = new Set();
    #uploaders = new Set();
    #postProcessors = new Set();
    defaultLocale;
    locale;
    // The user optionally passes in options, but we set defaults for missing options.
    // We consider all options present after the contructor has run.
    opts;
    store;
    // Warning: do not use this from a plugin, as it will cause the plugins' translations to be missing
    i18n;
    i18nArray;
    scheduledAutoProceed = null;
    wasOffline = false;
    /**
     * Instantiate Uppy
     */
    constructor(opts) {
        this.defaultLocale = _locale_js__WEBPACK_IMPORTED_MODULE_10__["default"];
        const defaultOptions = {
            id: 'uppy',
            autoProceed: false,
            allowMultipleUploadBatches: true,
            debug: false,
            restrictions: _Restricter_js__WEBPACK_IMPORTED_MODULE_12__.defaultOptions,
            meta: {},
            onBeforeFileAdded: (file, files) => !Object.hasOwn(files, file.id),
            onBeforeUpload: (files) => files,
            store: new _uppy_store_default__WEBPACK_IMPORTED_MODULE_0__["default"](),
            logger: _loggers_js__WEBPACK_IMPORTED_MODULE_11__.justErrorsLogger,
            infoTimeout: 5000,
        };
        const merged = { ...defaultOptions, ...opts };
        // Merge default options with the ones set by user,
        // making sure to merge restrictions too
        this.opts = {
            ...merged,
            restrictions: {
                ...defaultOptions.restrictions,
                ...opts?.restrictions,
            },
        };
        // Support debug: true for backwards-compatability, unless logger is set in opts
        // opts instead of this.opts to avoid comparing objects — we set logger: justErrorsLogger in defaultOptions
        if (opts?.logger && opts.debug) {
            this.log('You are using a custom `logger`, but also set `debug: true`, which uses built-in logger to output logs to console. Ignoring `debug: true` and using your custom `logger`.', 'warning');
        }
        else if (opts?.debug) {
            this.opts.logger = _loggers_js__WEBPACK_IMPORTED_MODULE_11__.debugLogger;
        }
        this.log(`Using Core v${Uppy.VERSION}`);
        this.i18nInit();
        this.store = this.opts.store;
        this.setState({
            ...defaultUploadState,
            plugins: {},
            files: {},
            currentUploads: {},
            capabilities: {
                uploadProgress: (0,_supportsUploadProgress_js__WEBPACK_IMPORTED_MODULE_13__["default"])(),
                individualCancellation: true,
                resumableUploads: false,
            },
            meta: { ...this.opts.meta },
            info: [],
        });
        this.#restricter = new _Restricter_js__WEBPACK_IMPORTED_MODULE_12__.Restricter(() => this.opts, () => this.i18n);
        this.#storeUnsubscribe = this.store.subscribe((prevState, nextState, patch) => {
            this.emit('state-update', prevState, nextState, patch);
            this.updateAll(nextState);
        });
        // Exposing uppy object on window for debugging and testing
        if (this.opts.debug && typeof window !== 'undefined') {
            // @ts-ignore Mutating the global object for debug purposes
            window[this.opts.id] = this;
        }
        this.#addListeners();
    }
    emit(event, ...args) {
        this.#emitter.emit(event, ...args);
    }
    on(event, callback) {
        this.#emitter.on(event, callback);
        return this;
    }
    once(event, callback) {
        this.#emitter.once(event, callback);
        return this;
    }
    off(event, callback) {
        this.#emitter.off(event, callback);
        return this;
    }
    /**
     * Iterate on all plugins and run `update` on them.
     * Called each time state changes.
     *
     */
    updateAll(state) {
        this.iteratePlugins((plugin) => {
            plugin.update(state);
        });
    }
    /**
     * Updates state with a patch
     */
    setState(patch) {
        this.store.setState(patch);
    }
    /**
     * Returns current state.
     */
    getState() {
        return this.store.getState();
    }
    patchFilesState(filesWithNewState) {
        const existingFilesState = this.getState().files;
        this.setState({
            files: {
                ...existingFilesState,
                ...Object.fromEntries(Object.entries(filesWithNewState).map(([fileID, newFileState]) => [
                    fileID,
                    {
                        ...existingFilesState[fileID],
                        ...newFileState,
                    },
                ])),
            },
        });
    }
    /**
     * Shorthand to set state for a specific file.
     */
    setFileState(fileID, state) {
        if (!this.getState().files[fileID]) {
            throw new Error(`Can’t set state for ${fileID} (the file could have been removed)`);
        }
        this.patchFilesState({ [fileID]: state });
    }
    i18nInit() {
        const onMissingKey = (key) => this.log(`Missing i18n string: ${key}`, 'error');
        const translator = new _uppy_utils_lib_Translator__WEBPACK_IMPORTED_MODULE_4__["default"]([this.defaultLocale, this.opts.locale], {
            onMissingKey,
        });
        this.i18n = translator.translate.bind(translator);
        this.i18nArray = translator.translateArray.bind(translator);
        this.locale = translator.locale;
    }
    setOptions(newOpts) {
        this.opts = {
            ...this.opts,
            ...newOpts,
            restrictions: {
                ...this.opts.restrictions,
                ...newOpts?.restrictions,
            },
        };
        if (newOpts.meta) {
            this.setMeta(newOpts.meta);
        }
        this.i18nInit();
        if (newOpts.locale) {
            this.iteratePlugins((plugin) => {
                plugin.setOptions(newOpts);
            });
        }
        // Note: this is not the preact `setState`, it's an internal function that has the same name.
        this.setState(undefined); // so that UI re-renders with new options
    }
    resetProgress() {
        const defaultProgress = {
            percentage: 0,
            bytesUploaded: false,
            uploadComplete: false,
            uploadStarted: null,
        };
        const files = { ...this.getState().files };
        const updatedFiles = Object.create(null);
        Object.keys(files).forEach((fileID) => {
            updatedFiles[fileID] = {
                ...files[fileID],
                progress: {
                    ...files[fileID].progress,
                    ...defaultProgress,
                },
                // @ts-expect-error these typed are inserted
                // into the namespace in their respective packages
                // but core isn't ware of those
                tus: undefined,
                transloadit: undefined,
            };
        });
        this.setState({ files: updatedFiles, ...defaultUploadState });
    }
    clear() {
        const { capabilities, currentUploads } = this.getState();
        if (Object.keys(currentUploads).length > 0 &&
            !capabilities.individualCancellation) {
            throw new Error('The installed uploader plugin does not allow removing files during an upload.');
        }
        this.setState({ ...defaultUploadState, files: {} });
    }
    addPreProcessor(fn) {
        this.#preProcessors.add(fn);
    }
    removePreProcessor(fn) {
        return this.#preProcessors.delete(fn);
    }
    addPostProcessor(fn) {
        this.#postProcessors.add(fn);
    }
    removePostProcessor(fn) {
        return this.#postProcessors.delete(fn);
    }
    addUploader(fn) {
        this.#uploaders.add(fn);
    }
    removeUploader(fn) {
        return this.#uploaders.delete(fn);
    }
    setMeta(data) {
        const updatedMeta = { ...this.getState().meta, ...data };
        const updatedFiles = { ...this.getState().files };
        Object.keys(updatedFiles).forEach((fileID) => {
            updatedFiles[fileID] = {
                ...updatedFiles[fileID],
                meta: { ...updatedFiles[fileID].meta, ...data },
            };
        });
        this.log('Adding metadata:');
        this.log(data);
        this.setState({
            meta: updatedMeta,
            files: updatedFiles,
        });
    }
    setFileMeta(fileID, data) {
        const updatedFiles = { ...this.getState().files };
        if (!updatedFiles[fileID]) {
            this.log(`Was trying to set metadata for a file that has been removed: ${fileID}`);
            return;
        }
        const newMeta = { ...updatedFiles[fileID].meta, ...data };
        updatedFiles[fileID] = { ...updatedFiles[fileID], meta: newMeta };
        this.setState({ files: updatedFiles });
    }
    /**
     * Get a file object.
     */
    getFile(fileID) {
        return this.getState().files[fileID];
    }
    /**
     * Get all files in an array.
     */
    getFiles() {
        const { files } = this.getState();
        return Object.values(files);
    }
    getFilesByIds(ids) {
        return ids.map((id) => this.getFile(id));
    }
    getObjectOfFilesPerState() {
        const { files: filesObject, totalProgress, error } = this.getState();
        const files = Object.values(filesObject);
        const inProgressFiles = [];
        const newFiles = [];
        const startedFiles = [];
        const uploadStartedFiles = [];
        const pausedFiles = [];
        const completeFiles = [];
        const erroredFiles = [];
        const inProgressNotPausedFiles = [];
        const processingFiles = [];
        for (const file of files) {
            const { progress } = file;
            if (!progress.uploadComplete && progress.uploadStarted) {
                inProgressFiles.push(file);
                if (!file.isPaused) {
                    inProgressNotPausedFiles.push(file);
                }
            }
            if (!progress.uploadStarted) {
                newFiles.push(file);
            }
            if (progress.uploadStarted ||
                progress.preprocess ||
                progress.postprocess) {
                startedFiles.push(file);
            }
            if (progress.uploadStarted) {
                uploadStartedFiles.push(file);
            }
            if (file.isPaused) {
                pausedFiles.push(file);
            }
            if (progress.uploadComplete) {
                completeFiles.push(file);
            }
            if (file.error) {
                erroredFiles.push(file);
            }
            if (progress.preprocess || progress.postprocess) {
                processingFiles.push(file);
            }
        }
        return {
            newFiles,
            startedFiles,
            uploadStartedFiles,
            pausedFiles,
            completeFiles,
            erroredFiles,
            inProgressFiles,
            inProgressNotPausedFiles,
            processingFiles,
            isUploadStarted: uploadStartedFiles.length > 0,
            isAllComplete: totalProgress === 100 &&
                completeFiles.length === files.length &&
                processingFiles.length === 0,
            isAllErrored: !!error && erroredFiles.length === files.length,
            isAllPaused: inProgressFiles.length !== 0 &&
                pausedFiles.length === inProgressFiles.length,
            isUploadInProgress: inProgressFiles.length > 0,
            isSomeGhost: files.some((file) => file.isGhost),
        };
    }
    #informAndEmit(errors) {
        for (const error of errors) {
            if (error.isRestriction) {
                this.emit('restriction-failed', error.file, error);
            }
            else {
                this.emit('error', error, error.file);
            }
            this.log(error, 'warning');
        }
        const userFacingErrors = errors.filter((error) => error.isUserFacing);
        // don't flood the user: only show the first 4 toasts
        const maxNumToShow = 4;
        const firstErrors = userFacingErrors.slice(0, maxNumToShow);
        const additionalErrors = userFacingErrors.slice(maxNumToShow);
        firstErrors.forEach(({ message, details = '' }) => {
            this.info({ message, details }, 'error', this.opts.infoTimeout);
        });
        if (additionalErrors.length > 0) {
            this.info({
                message: this.i18n('additionalRestrictionsFailed', {
                    count: additionalErrors.length,
                }),
            });
        }
    }
    validateRestrictions(file, files = this.getFiles()) {
        try {
            this.#restricter.validate(files, [file]);
        }
        catch (err) {
            return err;
        }
        return null;
    }
    validateSingleFile(file) {
        try {
            this.#restricter.validateSingleFile(file);
        }
        catch (err) {
            return err.message;
        }
        return null;
    }
    validateAggregateRestrictions(files) {
        const existingFiles = this.getFiles();
        try {
            this.#restricter.validateAggregateRestrictions(existingFiles, files);
        }
        catch (err) {
            return err.message;
        }
        return null;
    }
    #checkRequiredMetaFieldsOnFile(file) {
        const { missingFields, error } = this.#restricter.getMissingRequiredMetaFields(file);
        if (missingFields.length > 0) {
            this.setFileState(file.id, {
                missingRequiredMetaFields: missingFields,
                error: error.message,
            });
            this.log(error.message);
            this.emit('restriction-failed', file, error);
            return false;
        }
        if (missingFields.length === 0 && file.missingRequiredMetaFields) {
            this.setFileState(file.id, {
                missingRequiredMetaFields: [],
            });
        }
        return true;
    }
    #checkRequiredMetaFields(files) {
        let success = true;
        for (const file of Object.values(files)) {
            if (!this.#checkRequiredMetaFieldsOnFile(file)) {
                success = false;
            }
        }
        return success;
    }
    #assertNewUploadAllowed(file) {
        const { allowNewUpload } = this.getState();
        if (allowNewUpload === false) {
            const error = new _Restricter_js__WEBPACK_IMPORTED_MODULE_12__.RestrictionError(this.i18n('noMoreFilesAllowed'), {
                file,
            });
            this.#informAndEmit([error]);
            throw error;
        }
    }
    checkIfFileAlreadyExists(fileID) {
        const { files } = this.getState();
        if (files[fileID] && !files[fileID].isGhost) {
            return true;
        }
        return false;
    }
    /**
     * Create a file state object based on user-provided `addFile()` options.
     */
    #transformFile(fileDescriptorOrFile) {
        // Uppy expects files in { name, type, size, data } format.
        // If the actual File object is passed from input[type=file] or drag-drop,
        // we normalize it to match Uppy file object
        const file = (fileDescriptorOrFile instanceof File
            ? {
                name: fileDescriptorOrFile.name,
                type: fileDescriptorOrFile.type,
                size: fileDescriptorOrFile.size,
                data: fileDescriptorOrFile,
            }
            : fileDescriptorOrFile);
        const fileType = (0,_uppy_utils_lib_getFileType__WEBPACK_IMPORTED_MODULE_3__["default"])(file);
        const fileName = (0,_getFileName_js__WEBPACK_IMPORTED_MODULE_9__["default"])(fileType, file);
        const fileExtension = (0,_uppy_utils_lib_getFileNameAndExtension__WEBPACK_IMPORTED_MODULE_2__["default"])(fileName).extension;
        const id = (0,_uppy_utils_lib_generateFileID__WEBPACK_IMPORTED_MODULE_1__.getSafeFileId)(file, this.getID());
        const meta = file.meta || {};
        meta.name = fileName;
        meta.type = fileType;
        // `null` means the size is unknown.
        const size = Number.isFinite(file.data.size)
            ? file.data.size
            : null;
        return {
            source: file.source || '',
            id,
            name: fileName,
            extension: fileExtension || '',
            meta: {
                ...this.getState().meta,
                ...meta,
            },
            type: fileType,
            data: file.data,
            progress: {
                percentage: 0,
                bytesUploaded: false,
                bytesTotal: size,
                uploadComplete: false,
                uploadStarted: null,
            },
            size,
            isGhost: false,
            isRemote: file.isRemote || false,
            remote: file.remote,
            preview: file.preview,
        };
    }
    // Schedule an upload if `autoProceed` is enabled.
    #startIfAutoProceed() {
        if (this.opts.autoProceed && !this.scheduledAutoProceed) {
            this.scheduledAutoProceed = setTimeout(() => {
                this.scheduledAutoProceed = null;
                this.upload().catch((err) => {
                    if (!err.isRestriction) {
                        this.log(err.stack || err.message || err);
                    }
                });
            }, 4);
        }
    }
    #checkAndUpdateFileState(filesToAdd) {
        let { files: existingFiles } = this.getState();
        // create a copy of the files object only once
        let nextFilesState = { ...existingFiles };
        const validFilesToAdd = [];
        const errors = [];
        for (const fileToAdd of filesToAdd) {
            try {
                let newFile = this.#transformFile(fileToAdd);
                // If a file has been recovered (Golden Retriever), but we were unable to recover its data (probably too large),
                // users are asked to re-select these half-recovered files and then this method will be called again.
                // In order to keep the progress, meta and everything else, we keep the existing file,
                // but we replace `data`, and we remove `isGhost`, because the file is no longer a ghost now
                const isGhost = existingFiles[newFile.id]?.isGhost;
                if (isGhost) {
                    const existingFileState = existingFiles[newFile.id];
                    newFile = {
                        ...existingFileState,
                        isGhost: false,
                        data: fileToAdd.data,
                    };
                    this.log(`Replaced the blob in the restored ghost file: ${newFile.name}, ${newFile.id}`);
                }
                const onBeforeFileAddedResult = this.opts.onBeforeFileAdded(newFile, nextFilesState);
                // update state after onBeforeFileAdded
                existingFiles = this.getState().files;
                nextFilesState = { ...existingFiles, ...nextFilesState };
                if (!onBeforeFileAddedResult &&
                    this.checkIfFileAlreadyExists(newFile.id)) {
                    throw new _Restricter_js__WEBPACK_IMPORTED_MODULE_12__.RestrictionError(this.i18n('noDuplicates', {
                        fileName: newFile.name ?? this.i18n('unnamed'),
                    }), { file: fileToAdd });
                }
                // Pass through reselected files from Golden Retriever
                if (onBeforeFileAddedResult === false && !isGhost) {
                    // Don’t show UI info for this error, as it should be done by the developer
                    throw new _Restricter_js__WEBPACK_IMPORTED_MODULE_12__.RestrictionError('Cannot add the file because onBeforeFileAdded returned false.', { isUserFacing: false, file: fileToAdd });
                }
                else if (typeof onBeforeFileAddedResult === 'object' &&
                    onBeforeFileAddedResult !== null) {
                    newFile = onBeforeFileAddedResult;
                }
                this.#restricter.validateSingleFile(newFile);
                // need to add it to the new local state immediately, so we can use the state to validate the next files too
                nextFilesState[newFile.id] = newFile;
                validFilesToAdd.push(newFile);
            }
            catch (err) {
                errors.push(err);
            }
        }
        try {
            // need to run this separately because it's much more slow, so if we run it inside the for-loop it will be very slow
            // when many files are added
            this.#restricter.validateAggregateRestrictions(Object.values(existingFiles), validFilesToAdd);
        }
        catch (err) {
            errors.push(err);
            // If we have any aggregate error, don't allow adding this batch
            return {
                nextFilesState: existingFiles,
                validFilesToAdd: [],
                errors,
            };
        }
        return {
            nextFilesState,
            validFilesToAdd,
            errors,
        };
    }
    /**
     * Add a new file to `state.files`. This will run `onBeforeFileAdded`,
     * try to guess file type in a clever way, check file against restrictions,
     * and start an upload if `autoProceed === true`.
     */
    addFile(file) {
        this.#assertNewUploadAllowed(file);
        const { nextFilesState, validFilesToAdd, errors } = this.#checkAndUpdateFileState([file]);
        const restrictionErrors = errors.filter((error) => error.isRestriction);
        this.#informAndEmit(restrictionErrors);
        if (errors.length > 0)
            throw errors[0];
        this.setState({ files: nextFilesState });
        const [firstValidFileToAdd] = validFilesToAdd;
        this.emit('file-added', firstValidFileToAdd);
        this.emit('files-added', validFilesToAdd);
        this.log(`Added file: ${firstValidFileToAdd.name}, ${firstValidFileToAdd.id}, mime type: ${firstValidFileToAdd.type}`);
        this.#startIfAutoProceed();
        return firstValidFileToAdd.id;
    }
    /**
     * Add multiple files to `state.files`. See the `addFile()` documentation.
     *
     * If an error occurs while adding a file, it is logged and the user is notified.
     * This is good for UI plugins, but not for programmatic use.
     * Programmatic users should usually still use `addFile()` on individual files.
     */
    addFiles(fileDescriptors) {
        this.#assertNewUploadAllowed();
        const { nextFilesState, validFilesToAdd, errors } = this.#checkAndUpdateFileState(fileDescriptors);
        const restrictionErrors = errors.filter((error) => error.isRestriction);
        this.#informAndEmit(restrictionErrors);
        const nonRestrictionErrors = errors.filter((error) => !error.isRestriction);
        if (nonRestrictionErrors.length > 0) {
            let message = 'Multiple errors occurred while adding files:\n';
            nonRestrictionErrors.forEach((subError) => {
                message += `\n * ${subError.message}`;
            });
            this.info({
                message: this.i18n('addBulkFilesFailed', {
                    smart_count: nonRestrictionErrors.length,
                }),
                details: message,
            }, 'error', this.opts.infoTimeout);
            if (typeof AggregateError === 'function') {
                throw new AggregateError(nonRestrictionErrors, message);
            }
            else {
                const err = new Error(message);
                // @ts-expect-error fallback when AggregateError is not available
                err.errors = nonRestrictionErrors;
                throw err;
            }
        }
        // OK, we haven't thrown an error, we can start updating state and emitting events now:
        this.setState({ files: nextFilesState });
        validFilesToAdd.forEach((file) => {
            this.emit('file-added', file);
        });
        this.emit('files-added', validFilesToAdd);
        if (validFilesToAdd.length > 5) {
            this.log(`Added batch of ${validFilesToAdd.length} files`);
        }
        else {
            Object.values(validFilesToAdd).forEach((file) => {
                this.log(`Added file: ${file.name}\n id: ${file.id}\n type: ${file.type}`);
            });
        }
        if (validFilesToAdd.length > 0) {
            this.#startIfAutoProceed();
        }
    }
    removeFiles(fileIDs) {
        const { files, currentUploads } = this.getState();
        const updatedFiles = { ...files };
        const updatedUploads = { ...currentUploads };
        const removedFiles = Object.create(null);
        fileIDs.forEach((fileID) => {
            if (files[fileID]) {
                removedFiles[fileID] = files[fileID];
                delete updatedFiles[fileID];
            }
        });
        // Remove files from the `fileIDs` list in each upload.
        function fileIsNotRemoved(uploadFileID) {
            return removedFiles[uploadFileID] === undefined;
        }
        Object.keys(updatedUploads).forEach((uploadID) => {
            const newFileIDs = currentUploads[uploadID].fileIDs.filter(fileIsNotRemoved);
            // Remove the upload if no files are associated with it anymore.
            if (newFileIDs.length === 0) {
                delete updatedUploads[uploadID];
                return;
            }
            const { capabilities } = this.getState();
            if (newFileIDs.length !== currentUploads[uploadID].fileIDs.length &&
                !capabilities.individualCancellation) {
                throw new Error('The installed uploader plugin does not allow removing files during an upload.');
            }
            updatedUploads[uploadID] = {
                ...currentUploads[uploadID],
                fileIDs: newFileIDs,
            };
        });
        const stateUpdate = {
            currentUploads: updatedUploads,
            files: updatedFiles,
        };
        // If all files were removed - allow new uploads,
        // and clear recoveredState
        if (Object.keys(updatedFiles).length === 0) {
            stateUpdate.allowNewUpload = true;
            stateUpdate.error = null;
            stateUpdate.recoveredState = null;
        }
        this.setState(stateUpdate);
        this.#updateTotalProgressThrottled();
        const removedFileIDs = Object.keys(removedFiles);
        removedFileIDs.forEach((fileID) => {
            this.emit('file-removed', removedFiles[fileID]);
        });
        if (removedFileIDs.length > 5) {
            this.log(`Removed ${removedFileIDs.length} files`);
        }
        else {
            this.log(`Removed files: ${removedFileIDs.join(', ')}`);
        }
    }
    removeFile(fileID) {
        this.removeFiles([fileID]);
    }
    pauseResume(fileID) {
        if (!this.getState().capabilities.resumableUploads ||
            this.getFile(fileID).progress.uploadComplete) {
            return undefined;
        }
        const file = this.getFile(fileID);
        const wasPaused = file.isPaused || false;
        const isPaused = !wasPaused;
        this.setFileState(fileID, {
            isPaused,
        });
        this.emit('upload-pause', file, isPaused);
        return isPaused;
    }
    pauseAll() {
        const updatedFiles = { ...this.getState().files };
        const inProgressUpdatedFiles = Object.keys(updatedFiles).filter((file) => {
            return (!updatedFiles[file].progress.uploadComplete &&
                updatedFiles[file].progress.uploadStarted);
        });
        inProgressUpdatedFiles.forEach((file) => {
            const updatedFile = { ...updatedFiles[file], isPaused: true };
            updatedFiles[file] = updatedFile;
        });
        this.setState({ files: updatedFiles });
        this.emit('pause-all');
    }
    resumeAll() {
        const updatedFiles = { ...this.getState().files };
        const inProgressUpdatedFiles = Object.keys(updatedFiles).filter((file) => {
            return (!updatedFiles[file].progress.uploadComplete &&
                updatedFiles[file].progress.uploadStarted);
        });
        inProgressUpdatedFiles.forEach((file) => {
            const updatedFile = {
                ...updatedFiles[file],
                isPaused: false,
                error: null,
            };
            updatedFiles[file] = updatedFile;
        });
        this.setState({ files: updatedFiles });
        this.emit('resume-all');
    }
    #getFilesToRetry() {
        const { files } = this.getState();
        return Object.keys(files).filter((fileId) => {
            const file = files[fileId];
            // Only retry files that have errors AND don't have missing required metadata
            return (file.error &&
                (!file.missingRequiredMetaFields ||
                    file.missingRequiredMetaFields.length === 0));
        });
    }
    async #doRetryAll() {
        const filesToRetry = this.#getFilesToRetry();
        const updatedFiles = { ...this.getState().files };
        filesToRetry.forEach((fileID) => {
            updatedFiles[fileID] = {
                ...updatedFiles[fileID],
                isPaused: false,
                error: null,
            };
        });
        this.setState({
            files: updatedFiles,
            error: null,
        });
        this.emit('retry-all', this.getFilesByIds(filesToRetry));
        if (filesToRetry.length === 0) {
            return {
                successful: [],
                failed: [],
            };
        }
        const uploadID = this.#createUpload(filesToRetry, {
            forceAllowNewUpload: true, // create new upload even if allowNewUpload: false
        });
        return this.#runUpload(uploadID);
    }
    async retryAll() {
        const result = await this.#doRetryAll();
        this.emit('complete', result);
        return result;
    }
    cancelAll() {
        this.emit('cancel-all');
        const { files } = this.getState();
        const fileIDs = Object.keys(files);
        if (fileIDs.length) {
            this.removeFiles(fileIDs);
        }
        this.setState(defaultUploadState);
    }
    retryUpload(fileID) {
        this.setFileState(fileID, {
            error: null,
            isPaused: false,
        });
        this.emit('upload-retry', this.getFile(fileID));
        const uploadID = this.#createUpload([fileID], {
            forceAllowNewUpload: true, // create new upload even if allowNewUpload: false
        });
        return this.#runUpload(uploadID);
    }
    logout() {
        this.iteratePlugins((plugin) => {
            ;
            plugin.provider?.logout?.();
        });
    }
    #handleUploadProgress = (file, progress) => {
        const fileInState = file ? this.getFile(file.id) : undefined;
        if (file == null || !fileInState) {
            this.log(`Not setting progress for a file that has been removed: ${file?.id}`);
            return;
        }
        if (fileInState.progress.percentage === 100) {
            this.log(`Not setting progress for a file that has been already uploaded: ${file.id}`);
            return;
        }
        const newProgress = {
            bytesTotal: progress.bytesTotal,
            // bytesTotal may be null or zero; in that case we can't divide by it
            percentage: progress.bytesTotal != null &&
                Number.isFinite(progress.bytesTotal) &&
                progress.bytesTotal > 0
                ? Math.round((progress.bytesUploaded / progress.bytesTotal) * 100)
                : undefined,
        };
        if (fileInState.progress.uploadStarted != null) {
            this.setFileState(file.id, {
                progress: {
                    ...fileInState.progress,
                    ...newProgress,
                    bytesUploaded: progress.bytesUploaded,
                },
            });
        }
        else {
            this.setFileState(file.id, {
                progress: {
                    ...fileInState.progress,
                    ...newProgress,
                },
            });
        }
        this.#updateTotalProgressThrottled();
    };
    #updateTotalProgress() {
        const totalProgress = this.#calculateTotalProgress();
        let totalProgressPercent = null;
        if (totalProgress != null) {
            totalProgressPercent = Math.round(totalProgress * 100);
            if (totalProgressPercent > 100)
                totalProgressPercent = 100;
            else if (totalProgressPercent < 0)
                totalProgressPercent = 0;
        }
        this.emit('progress', totalProgressPercent ?? 0);
        this.setState({
            totalProgress: totalProgressPercent ?? 0,
        });
    }
    // ___Why throttle at 500ms?
    //    - We must throttle at >250ms for superfocus in Dashboard to work well
    //    (because animation takes 0.25s, and we want to wait for all animations to be over before refocusing).
    //    [Practical Check]: if thottle is at 100ms, then if you are uploading a file,
    //    and click 'ADD MORE FILES', - focus won't activate in Firefox.
    //    - We must throttle at around >500ms to avoid performance lags.
    //    [Practical Check] Firefox, try to upload a big file for a prolonged period of time. Laptop will start to heat up.
    #updateTotalProgressThrottled = lodash_throttle_js__WEBPACK_IMPORTED_MODULE_5__(() => this.#updateTotalProgress(), 500, { leading: true, trailing: true });
    [Symbol.for('uppy test: updateTotalProgress')]() {
        return this.#updateTotalProgress();
    }
    #calculateTotalProgress() {
        // calculate total progress, using the number of files currently uploading,
        // between 0 and 1 and sum of individual progress of each file
        const files = this.getFiles();
        // note: also includes files that have completed uploading:
        const filesInProgress = files.filter((file) => {
            return (file.progress.uploadStarted ||
                file.progress.preprocess ||
                file.progress.postprocess);
        });
        if (filesInProgress.length === 0) {
            return 0;
        }
        if (filesInProgress.every((file) => file.progress.uploadComplete)) {
            // If every uploading file is complete, and we're still getting progress, it probably means
            // there's a bug somewhere in some progress reporting code (maybe not even our code)
            // and we're still getting progress, so let's just assume it means a 100% progress
            return 1;
        }
        const isSizedFile = (file) => file.progress.bytesTotal != null && file.progress.bytesTotal !== 0;
        const sizedFilesInProgress = filesInProgress.filter(isSizedFile);
        const unsizedFilesInProgress = filesInProgress.filter((file) => !isSizedFile(file));
        if (sizedFilesInProgress.every((file) => file.progress.uploadComplete) &&
            unsizedFilesInProgress.length > 0 &&
            !unsizedFilesInProgress.every((file) => file.progress.uploadComplete)) {
            // we are done with uploading all files of known size, however
            // there is at least one file with unknown size still uploading,
            // and we cannot say anything about their progress
            // In any case, return null because it doesn't make any sense to show a progress
            return null;
        }
        const totalFilesSize = sizedFilesInProgress.reduce((acc, file) => acc + (file.progress.bytesTotal ?? 0), 0);
        const totalUploadedSize = sizedFilesInProgress.reduce((acc, file) => acc + (file.progress.bytesUploaded || 0), 0);
        return totalFilesSize === 0 ? 0 : totalUploadedSize / totalFilesSize;
    }
    /**
     * Registers listeners for all global actions, like:
     * `error`, `file-removed`, `upload-progress`
     */
    #addListeners() {
        // Type inference only works for inline functions so we have to type it again
        const errorHandler = (error, file, response) => {
            let errorMsg = error.message || 'Unknown error';
            if (error.details) {
                errorMsg += ` ${error.details}`;
            }
            this.setState({ error: errorMsg });
            if (file != null && file.id in this.getState().files) {
                this.setFileState(file.id, {
                    error: errorMsg,
                    response,
                });
            }
        };
        this.on('error', errorHandler);
        this.on('upload-error', (file, error, response) => {
            errorHandler(error, file, response);
            if (typeof error === 'object' && error.message) {
                this.log(error.message, 'error');
                const newError = new Error(this.i18n('failedToUpload', { file: file?.name ?? '' })); // we may want a new custom error here
                newError.isUserFacing = true; // todo maybe don't do this with all errors?
                newError.details = error.message;
                if (error.details) {
                    newError.details += ` ${error.details}`;
                }
                this.#informAndEmit([newError]);
            }
            else {
                this.#informAndEmit([error]);
            }
        });
        let uploadStalledWarningRecentlyEmitted = null;
        this.on('upload-stalled', (error, files) => {
            const { message } = error;
            const details = files.map((file) => file.meta.name).join(', ');
            if (!uploadStalledWarningRecentlyEmitted) {
                this.info({ message, details }, 'warning', this.opts.infoTimeout);
                uploadStalledWarningRecentlyEmitted = setTimeout(() => {
                    uploadStalledWarningRecentlyEmitted = null;
                }, this.opts.infoTimeout);
            }
            this.log(`${message} ${details}`.trim(), 'warning');
        });
        this.on('upload', () => {
            this.setState({ error: null });
        });
        const onUploadStarted = (files) => {
            const filesFiltered = files.filter((file) => {
                const exists = file != null && this.getFile(file.id);
                if (!exists)
                    this.log(`Not setting progress for a file that has been removed: ${file?.id}`);
                return exists;
            });
            const filesState = Object.fromEntries(filesFiltered.map((file) => [
                file.id,
                {
                    progress: {
                        uploadStarted: Date.now(),
                        uploadComplete: false,
                        bytesUploaded: 0,
                        bytesTotal: file.size,
                    },
                },
            ]));
            this.patchFilesState(filesState);
        };
        this.on('upload-start', onUploadStarted);
        this.on('upload-progress', this.#handleUploadProgress);
        this.on('upload-success', (file, uploadResp) => {
            if (file == null || !this.getFile(file.id)) {
                this.log(`Not setting progress for a file that has been removed: ${file?.id}`);
                return;
            }
            const currentProgress = this.getFile(file.id).progress;
            this.setFileState(file.id, {
                progress: {
                    ...currentProgress,
                    postprocess: this.#postProcessors.size > 0
                        ? {
                            mode: 'indeterminate',
                        }
                        : undefined,
                    uploadComplete: true,
                    percentage: 100,
                    bytesUploaded: currentProgress.bytesTotal,
                },
                response: uploadResp,
                uploadURL: uploadResp.uploadURL,
                isPaused: false,
            });
            // Remote providers sometimes don't tell us the file size,
            // but we can know how many bytes we uploaded once the upload is complete.
            if (file.size == null) {
                this.setFileState(file.id, {
                    size: uploadResp.bytesUploaded || currentProgress.bytesTotal,
                });
            }
            this.#updateTotalProgressThrottled();
        });
        this.on('preprocess-progress', (file, progress) => {
            if (file == null || !this.getFile(file.id)) {
                this.log(`Not setting progress for a file that has been removed: ${file?.id}`);
                return;
            }
            this.setFileState(file.id, {
                progress: { ...this.getFile(file.id).progress, preprocess: progress },
            });
        });
        this.on('preprocess-complete', (file) => {
            if (file == null || !this.getFile(file.id)) {
                this.log(`Not setting progress for a file that has been removed: ${file?.id}`);
                return;
            }
            const files = { ...this.getState().files };
            files[file.id] = {
                ...files[file.id],
                progress: { ...files[file.id].progress },
            };
            delete files[file.id].progress.preprocess;
            this.setState({ files });
        });
        this.on('postprocess-progress', (file, progress) => {
            if (file == null || !this.getFile(file.id)) {
                this.log(`Not setting progress for a file that has been removed: ${file?.id}`);
                return;
            }
            this.setFileState(file.id, {
                progress: {
                    ...this.getState().files[file.id].progress,
                    postprocess: progress,
                },
            });
        });
        this.on('postprocess-complete', (file) => {
            if (file == null || !this.getFile(file.id)) {
                this.log(`Not setting progress for a file that has been removed: ${file?.id}`);
                return;
            }
            const files = {
                ...this.getState().files,
            };
            files[file.id] = {
                ...files[file.id],
                progress: {
                    ...files[file.id].progress,
                },
            };
            delete files[file.id].progress.postprocess;
            this.setState({ files });
        });
        this.on('restored', () => {
            // Files may have changed--ensure progress is still accurate.
            this.#updateTotalProgressThrottled();
        });
        // @ts-expect-error should fix itself when dashboard it typed (also this doesn't belong here)
        this.on('dashboard:file-edit-complete', (file) => {
            if (file) {
                this.#checkRequiredMetaFieldsOnFile(file);
            }
        });
        // show informer if offline
        if (typeof window !== 'undefined' && window.addEventListener) {
            window.addEventListener('online', this.#updateOnlineStatus);
            window.addEventListener('offline', this.#updateOnlineStatus);
            setTimeout(this.#updateOnlineStatus, 3000);
        }
    }
    updateOnlineStatus() {
        const online = window.navigator.onLine ?? true;
        if (!online) {
            this.emit('is-offline');
            this.info(this.i18n('noInternetConnection'), 'error', 0);
            this.wasOffline = true;
        }
        else {
            this.emit('is-online');
            if (this.wasOffline) {
                this.emit('back-online');
                this.info(this.i18n('connectedToInternet'), 'success', 3000);
                this.wasOffline = false;
            }
        }
    }
    #updateOnlineStatus = this.updateOnlineStatus.bind(this);
    getID() {
        return this.opts.id;
    }
    /**
     * Registers a plugin with Core.
     */
    use(Plugin, 
    // We want to let the plugin decide whether `opts` is optional or not
    // so we spread the argument rather than defining `opts:` ourselves.
    ...args) {
        if (typeof Plugin !== 'function') {
            const msg = `Expected a plugin class, but got ${Plugin === null ? 'null' : typeof Plugin}.` +
                ' Please verify that the plugin was imported and spelled correctly.';
            throw new TypeError(msg);
        }
        // Instantiate
        const plugin = new Plugin(this, ...args);
        const pluginId = plugin.id;
        if (!pluginId) {
            throw new Error('Your plugin must have an id');
        }
        if (!plugin.type) {
            throw new Error('Your plugin must have a type');
        }
        const existsPluginAlready = this.getPlugin(pluginId);
        if (existsPluginAlready) {
            const msg = `Already found a plugin named '${existsPluginAlready.id}'. ` +
                `Tried to use: '${pluginId}'.\n` +
                'Uppy plugins must have unique `id` options.';
            throw new Error(msg);
        }
        // @ts-expect-error does exist
        if (Plugin.VERSION) {
            // @ts-expect-error does exist
            this.log(`Using ${pluginId} v${Plugin.VERSION}`);
        }
        if (plugin.type in this.#plugins) {
            this.#plugins[plugin.type].push(plugin);
        }
        else {
            this.#plugins[plugin.type] = [plugin];
        }
        plugin.install();
        this.emit('plugin-added', plugin);
        return this;
    }
    /**
     * Find one Plugin by name.
     */
    getPlugin(id) {
        for (const plugins of Object.values(this.#plugins)) {
            const foundPlugin = plugins.find((plugin) => plugin.id === id);
            if (foundPlugin != null)
                return foundPlugin;
        }
        return undefined;
    }
    [Symbol.for('uppy test: getPlugins')](type) {
        return this.#plugins[type];
    }
    /**
     * Iterate through all `use`d plugins.
     *
     */
    iteratePlugins(method) {
        Object.values(this.#plugins).flat(1).forEach(method);
    }
    /**
     * Uninstall and remove a plugin.
     *
     * @param {object} instance The plugin instance to remove.
     */
    removePlugin(instance) {
        this.log(`Removing plugin ${instance.id}`);
        this.emit('plugin-remove', instance);
        if (instance.uninstall) {
            instance.uninstall();
        }
        const list = this.#plugins[instance.type];
        // list.indexOf failed here, because Vue3 converted the plugin instance
        // to a Proxy object, which failed the strict comparison test:
        // obj !== objProxy
        const index = list.findIndex((item) => item.id === instance.id);
        if (index !== -1) {
            list.splice(index, 1);
        }
        const state = this.getState();
        const updatedState = {
            plugins: {
                ...state.plugins,
                [instance.id]: undefined,
            },
        };
        this.setState(updatedState);
    }
    /**
     * Uninstall all plugins and close down this Uppy instance.
     */
    destroy() {
        this.log(`Closing Uppy instance ${this.opts.id}: removing all files and uninstalling plugins`);
        this.cancelAll();
        this.#storeUnsubscribe();
        this.iteratePlugins((plugin) => {
            this.removePlugin(plugin);
        });
        if (typeof window !== 'undefined' && window.removeEventListener) {
            window.removeEventListener('online', this.#updateOnlineStatus);
            window.removeEventListener('offline', this.#updateOnlineStatus);
        }
    }
    hideInfo() {
        const { info } = this.getState();
        this.setState({ info: info.slice(1) });
        this.emit('info-hidden');
    }
    /**
     * Set info message in `state.info`, so that UI plugins like `Informer`
     * can display the message.
     */
    info(message, type = 'info', duration = 3000) {
        const isComplexMessage = typeof message === 'object';
        this.setState({
            info: [
                ...this.getState().info,
                {
                    type,
                    message: isComplexMessage ? message.message : message,
                    details: isComplexMessage ? message.details : null,
                },
            ],
        });
        setTimeout(() => this.hideInfo(), duration);
        this.emit('info-visible');
    }
    /**
     * Passes messages to a function, provided in `opts.logger`.
     * If `opts.logger: Uppy.debugLogger` or `opts.debug: true`, logs to the browser console.
     */
    log(message, type) {
        const { logger } = this.opts;
        switch (type) {
            case 'error':
                logger.error(message);
                break;
            case 'warning':
                logger.warn(message);
                break;
            default:
                logger.debug(message);
                break;
        }
    }
    // We need to store request clients by a unique ID, so we can share RequestClient instances across files
    // this allows us to do rate limiting and synchronous operations like refreshing provider tokens
    // example: refreshing tokens: if each file has their own requestclient,
    // we don't have any way to synchronize all requests in order to
    // - block all requests
    // - refresh the token
    // - unblock all requests and allow them to run with a the new access token
    // back when we had a requestclient per file, once an access token expired,
    // all 6 files would go ahead and refresh the token at the same time
    // (calling /refresh-token up to 6 times), which will probably fail for some providers
    #requestClientById = new Map();
    registerRequestClient(id, client) {
        this.#requestClientById.set(id, client);
    }
    /** @protected */
    getRequestClientForFile(file) {
        if (!file.remote)
            throw new Error(`Tried to get RequestClient for a non-remote file ${file.id}`);
        const requestClient = this.#requestClientById.get(file.remote.requestClientId);
        if (requestClient == null)
            throw new Error(`requestClientId "${file.remote.requestClientId}" not registered for file "${file.id}"`);
        return requestClient;
    }
    /**
     * Restore an upload by its ID.
     */
    restore(uploadID) {
        this.log(`Core: attempting to restore upload "${uploadID}"`);
        if (!this.getState().currentUploads[uploadID]) {
            this.#removeUpload(uploadID);
            return Promise.reject(new Error('Nonexistent upload'));
        }
        return this.#runUpload(uploadID);
    }
    /**
     * Create an upload for a bunch of files.
     *
     */
    #createUpload(fileIDs, opts = {}) {
        // uppy.retryAll sets this to true — when retrying we want to ignore `allowNewUpload: false`
        const { forceAllowNewUpload = false } = opts;
        const { allowNewUpload, currentUploads } = this.getState();
        if (!allowNewUpload && !forceAllowNewUpload) {
            throw new Error('Cannot create a new upload: already uploading.');
        }
        const uploadID = (0,nanoid_non_secure__WEBPACK_IMPORTED_MODULE_7__.nanoid)();
        this.emit('upload', uploadID, this.getFilesByIds(fileIDs));
        this.setState({
            allowNewUpload: this.opts.allowMultipleUploadBatches !== false &&
                this.opts.allowMultipleUploads !== false,
            currentUploads: {
                ...currentUploads,
                [uploadID]: {
                    fileIDs,
                    step: 0,
                    result: {},
                },
            },
        });
        return uploadID;
    }
    [Symbol.for('uppy test: createUpload')](...args) {
        // @ts-expect-error https://github.com/microsoft/TypeScript/issues/47595
        return this.#createUpload(...args);
    }
    #getUpload(uploadID) {
        const { currentUploads } = this.getState();
        return currentUploads[uploadID];
    }
    /**
     * Add data to an upload's result object.
     */
    addResultData(uploadID, data) {
        if (!this.#getUpload(uploadID)) {
            this.log(`Not setting result for an upload that has been removed: ${uploadID}`);
            return;
        }
        const { currentUploads } = this.getState();
        const currentUpload = {
            ...currentUploads[uploadID],
            result: { ...currentUploads[uploadID].result, ...data },
        };
        this.setState({
            currentUploads: { ...currentUploads, [uploadID]: currentUpload },
        });
    }
    /**
     * Remove an upload, eg. if it has been canceled or completed.
     *
     */
    #removeUpload(uploadID) {
        const currentUploads = { ...this.getState().currentUploads };
        delete currentUploads[uploadID];
        this.setState({
            currentUploads,
        });
    }
    /**
     * Run an upload. This picks up where it left off in case the upload is being restored.
     */
    async #runUpload(uploadID) {
        const getCurrentUpload = () => {
            const { currentUploads } = this.getState();
            return currentUploads[uploadID];
        };
        let currentUpload = getCurrentUpload();
        const steps = [
            ...this.#preProcessors,
            ...this.#uploaders,
            ...this.#postProcessors,
        ];
        try {
            for (let step = currentUpload.step || 0; step < steps.length; step++) {
                if (!currentUpload) {
                    break;
                }
                const fn = steps[step];
                this.setState({
                    currentUploads: {
                        ...this.getState().currentUploads,
                        [uploadID]: {
                            ...currentUpload,
                            step,
                        },
                    },
                });
                const { fileIDs } = currentUpload;
                // TODO give this the `updatedUpload` object as its only parameter maybe?
                // Otherwise when more metadata may be added to the upload this would keep getting more parameters
                await fn(fileIDs, uploadID);
                // Update currentUpload value in case it was modified asynchronously.
                currentUpload = getCurrentUpload();
            }
        }
        catch (err) {
            this.#removeUpload(uploadID);
            throw err;
        }
        // Set result data.
        if (currentUpload) {
            // Mark postprocessing step as complete if necessary; this addresses a case where we might get
            // stuck in the postprocessing UI while the upload is fully complete.
            // If the postprocessing steps do not do any work, they may not emit postprocessing events at
            // all, and never mark the postprocessing as complete. This is fine on its own but we
            // introduced code in the @uppy/core upload-success handler to prepare postprocessing progress
            // state if any postprocessors are registered. That is to avoid a "flash of completed state"
            // before the postprocessing plugins can emit events.
            //
            // So, just in case an upload with postprocessing plugins *has* completed *without* emitting
            // postprocessing completion, we do it instead.
            currentUpload.fileIDs.forEach((fileID) => {
                const file = this.getFile(fileID);
                if (file?.progress.postprocess) {
                    this.emit('postprocess-complete', file);
                }
            });
            const files = currentUpload.fileIDs.map((fileID) => this.getFile(fileID));
            const successful = files.filter((file) => !file.error);
            const failed = files.filter((file) => file.error);
            this.addResultData(uploadID, { successful, failed, uploadID });
            // Update currentUpload value in case it was modified asynchronously.
            currentUpload = getCurrentUpload();
        }
        // Emit completion events.
        // This is in a separate function so that the `currentUploads` variable
        // always refers to the latest state. In the handler right above it refers
        // to an outdated object without the `.result` property.
        let result;
        if (currentUpload) {
            result = currentUpload.result;
            this.#removeUpload(uploadID);
        }
        if (result == null) {
            this.log(`Not setting result for an upload that has been removed: ${uploadID}`);
            result = {
                successful: [],
                failed: [],
                uploadID,
            };
        }
        return result;
    }
    /**
     * Start an upload for all the files that are not currently being uploaded.
     */
    async upload() {
        if (!this.#plugins.uploader?.length) {
            this.log('No uploader type plugins are used', 'warning');
        }
        let { files } = this.getState();
        // retry any failed files from a previous upload() call
        const filesToRetry = this.#getFilesToRetry();
        if (filesToRetry.length > 0) {
            const retryResult = await this.#doRetryAll(); // we don't want the complete event to fire
            const hasNewFiles = this.getFiles().filter((file) => file.progress.uploadStarted == null)
                .length > 0;
            // if no new files, make it idempotent and return
            if (!hasNewFiles) {
                this.emit('complete', retryResult);
                return retryResult;
            }
            // reload files which might have  changed after retry
            ;
            ({ files } = this.getState());
        }
        // If no files to retry, proceed with original upload() behavior for new files
        const onBeforeUploadResult = this.opts.onBeforeUpload(files);
        if (onBeforeUploadResult === false) {
            return Promise.reject(new Error('Not starting the upload because onBeforeUpload returned false'));
        }
        if (onBeforeUploadResult && typeof onBeforeUploadResult === 'object') {
            files = onBeforeUploadResult;
            // Updating files in state, because uploader plugins receive file IDs,
            // and then fetch the actual file object from state
            this.setState({
                files,
            });
        }
        return Promise.resolve()
            .then(() => this.#restricter.validateMinNumberOfFiles(files))
            .catch((err) => {
            this.#informAndEmit([err]);
            throw err;
        })
            .then(() => {
            if (!this.#checkRequiredMetaFields(files)) {
                throw new _Restricter_js__WEBPACK_IMPORTED_MODULE_12__.RestrictionError(this.i18n('missingRequiredMetaField'));
            }
        })
            .catch((err) => {
            // Doing this in a separate catch because we already emited and logged
            // all the errors in `checkRequiredMetaFields` so we only throw a generic
            // missing fields error here.
            throw err;
        })
            .then(async () => {
            const { currentUploads } = this.getState();
            // get a list of files that are currently assigned to uploads
            const currentlyUploadingFiles = Object.values(currentUploads).flatMap((curr) => curr.fileIDs);
            const waitingFileIDs = [];
            Object.keys(files).forEach((fileID) => {
                const file = this.getFile(fileID);
                // if the file hasn't started uploading and hasn't already been assigned to an upload..
                if (!file.progress.uploadStarted &&
                    currentlyUploadingFiles.indexOf(fileID) === -1) {
                    waitingFileIDs.push(file.id);
                }
            });
            const uploadID = this.#createUpload(waitingFileIDs);
            const result = await this.#runUpload(uploadID);
            this.emit('complete', result);
            return result;
        })
            .catch((err) => {
            this.emit('error', err);
            this.log(err, 'error');
            throw err;
        });
    }
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Uppy);


/***/ },

/***/ "./node_modules/@uppy/core/lib/getFileName.js"
/*!****************************************************!*\
  !*** ./node_modules/@uppy/core/lib/getFileName.js ***!
  \****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getFileName)
/* harmony export */ });
function getFileName(fileType, fileDescriptor) {
    if (fileDescriptor.name) {
        return fileDescriptor.name;
    }
    if (fileType.split('/')[0] === 'image') {
        return `${fileType.split('/')[0]}.${fileType.split('/')[1]}`;
    }
    return 'noname';
}


/***/ },

/***/ "./node_modules/@uppy/core/lib/locale.js"
/*!***********************************************!*\
  !*** ./node_modules/@uppy/core/lib/locale.js ***!
  \***********************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
    strings: {
        addBulkFilesFailed: {
            0: 'Failed to add %{smart_count} file due to an internal error',
            1: 'Failed to add %{smart_count} files due to internal errors',
        },
        youCanOnlyUploadX: {
            0: 'You can only upload %{smart_count} file',
            1: 'You can only upload %{smart_count} files',
        },
        youHaveToAtLeastSelectX: {
            0: 'You have to select at least %{smart_count} file',
            1: 'You have to select at least %{smart_count} files',
        },
        aggregateExceedsSize: 'You selected %{size} of files, but maximum allowed size is %{sizeAllowed}',
        exceedsSize: '%{file} exceeds maximum allowed size of %{size}',
        missingRequiredMetaField: 'Missing required meta fields',
        missingRequiredMetaFieldOnFile: 'Missing required meta fields in %{fileName}',
        inferiorSize: 'This file is smaller than the allowed size of %{size}',
        youCanOnlyUploadFileTypes: 'You can only upload: %{types}',
        noMoreFilesAllowed: 'Cannot add more files',
        noDuplicates: "Cannot add the duplicate file '%{fileName}', it already exists",
        companionError: 'Connection with Companion failed',
        authAborted: 'Authentication aborted',
        companionUnauthorizeHint: 'To unauthorize to your %{provider} account, please go to %{url}',
        failedToUpload: 'Failed to upload %{file}',
        noInternetConnection: 'No Internet connection',
        connectedToInternet: 'Connected to the Internet',
        // Strings for remote providers
        noFilesFound: 'You have no files or folders here',
        noSearchResults: 'Unfortunately, there are no results for this search',
        selectX: {
            0: 'Select %{smart_count}',
            1: 'Select %{smart_count}',
        },
        allFilesFromFolderNamed: 'All files from folder %{name}',
        openFolderNamed: 'Open folder %{name}',
        cancel: 'Cancel',
        logOut: 'Log out',
        logIn: 'Log in',
        pickFiles: 'Pick files',
        pickPhotos: 'Pick photos',
        filter: 'Filter',
        resetFilter: 'Reset filter',
        loading: 'Loading...',
        loadedXFiles: 'Loaded %{numFiles} files',
        authenticateWithTitle: 'Please authenticate with %{pluginName} to select files',
        authenticateWith: 'Connect to %{pluginName}',
        signInWithGoogle: 'Sign in with Google',
        searchImages: 'Search for images',
        enterTextToSearch: 'Enter text to search for images',
        search: 'Search',
        resetSearch: 'Reset search',
        emptyFolderAdded: 'No files were added from empty folder',
        addedNumFiles: 'Added %{numFiles} file(s)',
        folderAlreadyAdded: 'The folder "%{folder}" was already added',
        folderAdded: {
            0: 'Added %{smart_count} file from %{folder}',
            1: 'Added %{smart_count} files from %{folder}',
        },
        additionalRestrictionsFailed: '%{count} additional restrictions were not fulfilled',
        unnamed: 'Unnamed',
        pleaseWait: 'Please wait',
    },
});


/***/ },

/***/ "./node_modules/@uppy/core/lib/loggers.js"
/*!************************************************!*\
  !*** ./node_modules/@uppy/core/lib/loggers.js ***!
  \************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   debugLogger: () => (/* binding */ debugLogger),
/* harmony export */   justErrorsLogger: () => (/* binding */ justErrorsLogger)
/* harmony export */ });
/* harmony import */ var _uppy_utils_lib_getTimeStamp__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @uppy/utils/lib/getTimeStamp */ "./node_modules/@uppy/utils/lib/getTimeStamp.js");

// Swallow all logs, except errors.
// default if logger is not set or debug: false
const justErrorsLogger = {
    debug: () => { },
    warn: () => { },
    error: (...args) => console.error(`[Uppy] [${(0,_uppy_utils_lib_getTimeStamp__WEBPACK_IMPORTED_MODULE_0__["default"])()}]`, ...args),
};
// Print logs to console with namespace + timestamp,
// set by logger: Uppy.debugLogger or debug: true
const debugLogger = {
    debug: (...args) => console.debug(`[Uppy] [${(0,_uppy_utils_lib_getTimeStamp__WEBPACK_IMPORTED_MODULE_0__["default"])()}]`, ...args),
    warn: (...args) => console.warn(`[Uppy] [${(0,_uppy_utils_lib_getTimeStamp__WEBPACK_IMPORTED_MODULE_0__["default"])()}]`, ...args),
    error: (...args) => console.error(`[Uppy] [${(0,_uppy_utils_lib_getTimeStamp__WEBPACK_IMPORTED_MODULE_0__["default"])()}]`, ...args),
};



/***/ },

/***/ "./node_modules/@uppy/core/lib/supportsUploadProgress.js"
/*!***************************************************************!*\
  !*** ./node_modules/@uppy/core/lib/supportsUploadProgress.js ***!
  \***************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ supportsUploadProgress)
/* harmony export */ });
// Edge 15.x does not fire 'progress' events on uploads.
// See https://github.com/transloadit/uppy/issues/945
// And https://developer.microsoft.com/en-us/microsoft-edge/platform/issues/12224510/
function supportsUploadProgress(userAgent) {
    // Allow passing in userAgent for tests
    if (userAgent == null && typeof navigator !== 'undefined') {
        userAgent = navigator.userAgent;
    }
    // Assume it works because basically everything supports progress events.
    if (!userAgent)
        return true;
    const m = /Edge\/(\d+\.\d+)/.exec(userAgent);
    if (!m)
        return true;
    const edgeVersion = m[1];
    const version = edgeVersion.split('.', 2);
    const major = parseInt(version[0], 10);
    const minor = parseInt(version[1], 10);
    // Worked before:
    // Edge 40.15063.0.0
    // Microsoft EdgeHTML 15.15063
    if (major < 15 || (major === 15 && minor < 15063)) {
        return true;
    }
    // Fixed in:
    // Microsoft EdgeHTML 18.18218
    if (major > 18 || (major === 18 && minor >= 18218)) {
        return true;
    }
    // other versions don't work.
    return false;
}


/***/ },

/***/ "./node_modules/@uppy/drag-drop/lib/DragDrop.js"
/*!******************************************************!*\
  !*** ./node_modules/@uppy/drag-drop/lib/DragDrop.js ***!
  \******************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ DragDrop)
/* harmony export */ });
/* harmony import */ var preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! preact/jsx-runtime */ "./node_modules/preact/jsx-runtime/dist/jsxRuntime.module.js");
/* harmony import */ var _uppy_core__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @uppy/core */ "./node_modules/@uppy/core/lib/UIPlugin.js");
/* harmony import */ var _uppy_utils_lib_getDroppedFiles__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @uppy/utils/lib/getDroppedFiles */ "./node_modules/@uppy/utils/lib/getDroppedFiles/index.js");
/* harmony import */ var _uppy_utils_lib_isDragDropSupported__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @uppy/utils/lib/isDragDropSupported */ "./node_modules/@uppy/utils/lib/isDragDropSupported.js");
/* harmony import */ var _uppy_utils_lib_toArray__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @uppy/utils/lib/toArray */ "./node_modules/@uppy/utils/lib/toArray.js");
/* harmony import */ var preact__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! preact */ "./node_modules/preact/dist/preact.module.js");
/* harmony import */ var _package_json__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../package.json */ "./node_modules/@uppy/drag-drop/package.json");
/* harmony import */ var _locale_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./locale.js */ "./node_modules/@uppy/drag-drop/lib/locale.js");








const defaultOptions = {
    inputName: 'files[]',
    width: '100%',
    height: '100%',
};
/**
 * Drag & Drop plugin
 *
 */
class DragDrop extends _uppy_core__WEBPACK_IMPORTED_MODULE_1__["default"] {
    static VERSION = _package_json__WEBPACK_IMPORTED_MODULE_6__.version;
    // Check for browser dragDrop support
    isDragDropSupported = (0,_uppy_utils_lib_isDragDropSupported__WEBPACK_IMPORTED_MODULE_3__["default"])();
    fileInputRef;
    constructor(uppy, opts) {
        super(uppy, {
            ...defaultOptions,
            ...opts,
        });
        this.type = 'acquirer';
        this.id = this.opts.id || 'DragDrop';
        this.title = 'Drag & Drop';
        this.defaultLocale = _locale_js__WEBPACK_IMPORTED_MODULE_7__["default"];
        this.i18nInit();
    }
    addFiles = (files) => {
        const descriptors = files.map((file) => ({
            source: this.id,
            name: file.name,
            type: file.type,
            data: file,
            meta: {
                // path of the file relative to the ancestor directory the user selected.
                // e.g. 'docs/Old Prague/airbnb.pdf'
                relativePath: file.relativePath || null,
            },
        }));
        try {
            this.uppy.addFiles(descriptors);
        }
        catch (err) {
            this.uppy.log(err);
        }
    };
    onInputChange = (event) => {
        const files = (0,_uppy_utils_lib_toArray__WEBPACK_IMPORTED_MODULE_4__["default"])(event.currentTarget.files || []);
        if (files.length > 0) {
            this.uppy.log('[DragDrop] Files selected through input');
            this.addFiles(files);
        }
        // Clear the input so that Chrome can detect file section when the same file is repeatedly selected
        // (see https://github.com/transloadit/uppy/issues/768#issuecomment-2264902758)
        event.currentTarget.value = '';
    };
    handleDragOver = (event) => {
        event.preventDefault();
        event.stopPropagation();
        // Check if the "type" of the datatransfer object includes files. If not, deny drop.
        const { types } = event.dataTransfer;
        const hasFiles = types.some((type) => type === 'Files');
        const { allowNewUpload } = this.uppy.getState();
        if (!hasFiles || !allowNewUpload) {
            event.dataTransfer.dropEffect = 'none';
            return;
        }
        // Add a small (+) icon on drop
        // (and prevent browsers from interpreting this as files being _moved_ into the browser
        // https://github.com/transloadit/uppy/issues/1978)
        //
        event.dataTransfer.dropEffect = 'copy';
        this.setPluginState({ isDraggingOver: true });
        this.opts.onDragOver?.(event);
    };
    handleDragLeave = (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.setPluginState({ isDraggingOver: false });
        this.opts.onDragLeave?.(event);
    };
    handleDrop = async (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.setPluginState({ isDraggingOver: false });
        const logDropError = (error) => {
            this.uppy.log(error, 'error');
        };
        // Add all dropped files
        const files = await (0,_uppy_utils_lib_getDroppedFiles__WEBPACK_IMPORTED_MODULE_2__["default"])(event.dataTransfer, { logDropError });
        if (files.length > 0) {
            this.uppy.log('[DragDrop] Files dropped');
            this.addFiles(files);
        }
        this.opts.onDrop?.(event);
    };
    renderHiddenFileInput() {
        const { restrictions } = this.uppy.opts;
        return ((0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("input", { className: "uppy-DragDrop-input", type: "file", hidden: true, ref: (ref) => {
                this.fileInputRef = ref;
            }, name: this.opts.inputName, multiple: restrictions.maxNumberOfFiles !== 1, accept: restrictions.allowedFileTypes?.join(', '), onChange: this.onInputChange }));
    }
    static renderArrowSvg() {
        return ((0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("svg", { "aria-hidden": "true", focusable: "false", className: "uppy-c-icon uppy-DragDrop-arrow", width: "16", height: "16", viewBox: "0 0 16 16", children: (0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", { d: "M11 10V0H5v10H2l6 6 6-6h-3zm0 0", fillRule: "evenodd" }) }));
    }
    renderLabel() {
        return ((0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("div", { className: "uppy-DragDrop-label", children: this.i18nArray('dropHereOr', {
                browse: ((0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("span", { className: "uppy-DragDrop-browse", children: this.i18n('browse') })),
            }) }));
    }
    renderNote() {
        return (0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("span", { className: "uppy-DragDrop-note", children: this.opts.note });
    }
    render() {
        const dragDropClass = `uppy-u-reset
      uppy-DragDrop-container
      ${this.isDragDropSupported ? 'uppy-DragDrop--isDragDropSupported' : ''}
      ${this.getPluginState().isDraggingOver ? 'uppy-DragDrop--isDraggingOver' : ''}
    `;
        const dragDropStyle = {
            width: this.opts.width,
            height: this.opts.height,
        };
        return ((0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("button", { type: "button", className: dragDropClass, style: dragDropStyle, onClick: () => this.fileInputRef.click(), onDragOver: this.handleDragOver, onDragLeave: this.handleDragLeave, onDrop: this.handleDrop, children: [this.renderHiddenFileInput(), (0,preact_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("div", { className: "uppy-DragDrop-inner", children: [DragDrop.renderArrowSvg(), this.renderLabel(), this.renderNote()] })] }));
    }
    install() {
        const { target } = this.opts;
        this.setPluginState({
            isDraggingOver: false,
        });
        if (target) {
            this.mount(target, this);
        }
    }
    uninstall() {
        this.unmount();
    }
}


/***/ },

/***/ "./node_modules/@uppy/drag-drop/lib/index.js"
/*!***************************************************!*\
  !*** ./node_modules/@uppy/drag-drop/lib/index.js ***!
  \***************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* reexport safe */ _DragDrop_js__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _DragDrop_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./DragDrop.js */ "./node_modules/@uppy/drag-drop/lib/DragDrop.js");



/***/ },

/***/ "./node_modules/@uppy/drag-drop/lib/locale.js"
/*!****************************************************!*\
  !*** ./node_modules/@uppy/drag-drop/lib/locale.js ***!
  \****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
    strings: {
        // Text to show on the droppable area.
        // `%{browse}` is replaced with a link that opens the system file selection dialog.
        dropHereOr: 'Drop here or %{browse}',
        // Used as the label for the link that opens the system file selection dialog.
        browse: 'browse',
    },
});


/***/ },

/***/ "./node_modules/@uppy/store-default/lib/index.js"
/*!*******************************************************!*\
  !*** ./node_modules/@uppy/store-default/lib/index.js ***!
  \*******************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _package_json__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../package.json */ "./node_modules/@uppy/store-default/package.json");

/**
 * Default store that keeps state in a simple object.
 */
class DefaultStore {
    static VERSION = _package_json__WEBPACK_IMPORTED_MODULE_0__.version;
    state = {};
    #callbacks = new Set();
    getState() {
        return this.state;
    }
    setState(patch) {
        const prevState = { ...this.state };
        const nextState = { ...this.state, ...patch };
        this.state = nextState;
        this.#publish(prevState, nextState, patch);
    }
    subscribe(listener) {
        this.#callbacks.add(listener);
        return () => {
            this.#callbacks.delete(listener);
        };
    }
    #publish(...args) {
        this.#callbacks.forEach((listener) => {
            listener(...args);
        });
    }
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DefaultStore);


/***/ },

/***/ "./node_modules/@uppy/utils/lib/NetworkError.js"
/*!******************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/NetworkError.js ***!
  \******************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
class NetworkError extends Error {
    cause;
    isNetworkError;
    request;
    constructor(error, xhr = null) {
        super(`This looks like a network error, the endpoint might be blocked by an internet provider or a firewall.`);
        this.cause = error;
        this.isNetworkError = true;
        this.request = xhr;
    }
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (NetworkError);


/***/ },

/***/ "./node_modules/@uppy/utils/lib/ProgressTimeout.js"
/*!*********************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/ProgressTimeout.js ***!
  \*********************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Helper to abort upload requests if there has not been any progress for `timeout` ms.
 * Create an instance using `timer = new ProgressTimeout(10000, onTimeout)`
 * Call `timer.progress()` to signal that there has been progress of any kind.
 * Call `timer.done()` when the upload has completed.
 */
class ProgressTimeout {
    #aliveTimer;
    #isDone = false;
    #onTimedOut;
    #timeout;
    constructor(timeout, timeoutHandler) {
        this.#timeout = timeout;
        this.#onTimedOut = () => timeoutHandler(timeout);
    }
    progress() {
        // Some browsers fire another progress event when the upload is
        // cancelled, so we have to ignore progress after the timer was
        // told to stop.
        if (this.#isDone)
            return;
        if (this.#timeout > 0) {
            clearTimeout(this.#aliveTimer);
            this.#aliveTimer = setTimeout(this.#onTimedOut, this.#timeout);
        }
    }
    done() {
        if (!this.#isDone) {
            clearTimeout(this.#aliveTimer);
            this.#aliveTimer = undefined;
            this.#isDone = true;
        }
    }
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ProgressTimeout);


/***/ },

/***/ "./node_modules/@uppy/utils/lib/RateLimitedQueue.js"
/*!**********************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/RateLimitedQueue.js ***!
  \**********************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   RateLimitedQueue: () => (/* binding */ RateLimitedQueue),
/* harmony export */   internalRateLimitedQueue: () => (/* binding */ internalRateLimitedQueue)
/* harmony export */ });
function createCancelError(cause) {
    return new Error('Cancelled', { cause });
}
function abortOn(signal) {
    if (signal != null) {
        const abortPromise = () => this.abort(signal.reason);
        signal.addEventListener('abort', abortPromise, { once: true });
        const removeAbortListener = () => {
            signal.removeEventListener('abort', abortPromise);
        };
        this.then?.(removeAbortListener, removeAbortListener);
    }
    return this;
}
class RateLimitedQueue {
    #activeRequests = 0;
    #queuedHandlers = [];
    #paused = false;
    #pauseTimer;
    #downLimit = 1;
    #upperLimit;
    #rateLimitingTimer;
    limit;
    constructor(limit) {
        if (typeof limit !== 'number' || limit === 0) {
            this.limit = Infinity;
        }
        else {
            this.limit = limit;
        }
    }
    #call(fn) {
        this.#activeRequests += 1;
        let done = false;
        let cancelActive;
        try {
            cancelActive = fn();
        }
        catch (err) {
            this.#activeRequests -= 1;
            throw err;
        }
        return {
            abort: (cause) => {
                if (done)
                    return;
                done = true;
                this.#activeRequests -= 1;
                cancelActive?.(cause);
                this.#queueNext();
            },
            done: () => {
                if (done)
                    return;
                done = true;
                this.#activeRequests -= 1;
                this.#queueNext();
            },
        };
    }
    #queueNext() {
        // Do it soon but not immediately, this allows clearing out the entire queue synchronously
        // one by one without continuously _advancing_ it (and starting new tasks before immediately
        // aborting them)
        queueMicrotask(() => this.#next());
    }
    #next() {
        if (this.#paused || this.#activeRequests >= this.limit) {
            return;
        }
        if (this.#queuedHandlers.length === 0) {
            return;
        }
        // Dispatch the next request, and update the abort/done handlers
        // so that cancelling it does the Right Thing (and doesn't just try
        // to dequeue an already-running request).
        const next = this.#queuedHandlers.shift();
        if (next == null) {
            throw new Error('Invariant violation: next is null');
        }
        const handler = this.#call(next.fn);
        next.abort = handler.abort;
        next.done = handler.done;
    }
    #queue(fn, options) {
        const handler = {
            fn,
            priority: options?.priority || 0,
            abort: () => {
                this.#dequeue(handler);
            },
            done: () => {
                throw new Error('Cannot mark a queued request as done: this indicates a bug');
            },
        };
        const index = this.#queuedHandlers.findIndex((other) => {
            return handler.priority > other.priority;
        });
        if (index === -1) {
            this.#queuedHandlers.push(handler);
        }
        else {
            this.#queuedHandlers.splice(index, 0, handler);
        }
        return handler;
    }
    #dequeue(handler) {
        const index = this.#queuedHandlers.indexOf(handler);
        if (index !== -1) {
            this.#queuedHandlers.splice(index, 1);
        }
    }
    run(fn, queueOptions) {
        if (!this.#paused && this.#activeRequests < this.limit) {
            return this.#call(fn);
        }
        return this.#queue(fn, queueOptions);
    }
    wrapSyncFunction(fn, queueOptions) {
        return (...args) => {
            const queuedRequest = this.run(() => {
                fn(...args);
                queueMicrotask(() => queuedRequest.done());
                return () => { };
            }, queueOptions);
            return {
                abortOn,
                abort() {
                    queuedRequest.abort();
                },
            };
        };
    }
    wrapPromiseFunction(fn, queueOptions) {
        return (...args) => {
            let queuedRequest;
            const outerPromise = new Promise((resolve, reject) => {
                queuedRequest = this.run(() => {
                    let cancelError;
                    let innerPromise;
                    try {
                        innerPromise = Promise.resolve(fn(...args));
                    }
                    catch (err) {
                        innerPromise = Promise.reject(err);
                    }
                    innerPromise.then((result) => {
                        if (cancelError) {
                            reject(cancelError);
                        }
                        else {
                            queuedRequest.done();
                            resolve(result);
                        }
                    }, (err) => {
                        if (cancelError) {
                            reject(cancelError);
                        }
                        else {
                            queuedRequest.done();
                            reject(err);
                        }
                    });
                    return (cause) => {
                        cancelError = createCancelError(cause);
                    };
                }, queueOptions);
            });
            outerPromise.abort = (cause) => {
                queuedRequest.abort(cause);
            };
            outerPromise.abortOn = abortOn;
            return outerPromise;
        };
    }
    resume() {
        this.#paused = false;
        clearTimeout(this.#pauseTimer);
        for (let i = 0; i < this.limit; i++) {
            this.#queueNext();
        }
    }
    #resume = () => this.resume();
    /**
     * Freezes the queue for a while or indefinitely.
     *
     * @param {number | null } [duration] Duration for the pause to happen, in milliseconds.
     *                                    If omitted, the queue won't resume automatically.
     */
    pause(duration = null) {
        this.#paused = true;
        clearTimeout(this.#pauseTimer);
        if (duration != null) {
            this.#pauseTimer = setTimeout(this.#resume, duration);
        }
    }
    /**
     * Pauses the queue for a duration, and lower the limit of concurrent requests
     * when the queue resumes. When the queue resumes, it tries to progressively
     * increase the limit in `this.#increaseLimit` until another call is made to
     * `this.rateLimit`.
     * Call this function when using the RateLimitedQueue for network requests and
     * the remote server responds with 429 HTTP code.
     *
     * @param {number} duration in milliseconds.
     */
    rateLimit(duration) {
        clearTimeout(this.#rateLimitingTimer);
        this.pause(duration);
        if (this.limit > 1 && Number.isFinite(this.limit)) {
            this.#upperLimit = this.limit - 1;
            this.limit = this.#downLimit;
            this.#rateLimitingTimer = setTimeout(this.#increaseLimit, duration);
        }
    }
    #increaseLimit = () => {
        if (this.#paused) {
            this.#rateLimitingTimer = setTimeout(this.#increaseLimit, 0);
            return;
        }
        this.#downLimit = this.limit;
        this.limit = Math.ceil((this.#upperLimit + this.#downLimit) / 2);
        for (let i = this.#downLimit; i <= this.limit; i++) {
            this.#queueNext();
        }
        if (this.#upperLimit - this.#downLimit > 3) {
            this.#rateLimitingTimer = setTimeout(this.#increaseLimit, 2000);
        }
        else {
            this.#downLimit = Math.floor(this.#downLimit / 2);
        }
    };
    get isPaused() {
        return this.#paused;
    }
}
const internalRateLimitedQueue = Symbol('__queue');


/***/ },

/***/ "./node_modules/@uppy/utils/lib/Translator.js"
/*!****************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/Translator.js ***!
  \****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Translator)
/* harmony export */ });
function insertReplacement(source, rx, replacement) {
    const newParts = [];
    source.forEach((chunk) => {
        // When the source contains multiple placeholders for interpolation,
        // we should ignore chunks that are not strings, because those
        // can be JSX objects and will be otherwise incorrectly turned into strings.
        // Without this condition we’d get this: [object Object] hello [object Object] my <button>
        if (typeof chunk !== 'string') {
            return newParts.push(chunk);
        }
        return rx[Symbol.split](chunk).forEach((raw, i, list) => {
            if (raw !== '') {
                newParts.push(raw);
            }
            // Interlace with the `replacement` value
            if (i < list.length - 1) {
                newParts.push(replacement);
            }
        });
    });
    return newParts;
}
/**
 * Takes a string with placeholder variables like `%{smart_count} file selected`
 * and replaces it with values from options `{smart_count: 5}`
 *
 * @license https://github.com/airbnb/polyglot.js/blob/master/LICENSE
 * taken from https://github.com/airbnb/polyglot.js/blob/master/lib/polyglot.js#L299
 *
 * @param phrase that needs interpolation, with placeholders
 * @param options with values that will be used to replace placeholders
 */
function interpolate(phrase, options) {
    const dollarRegex = /\$/g;
    const dollarBillsYall = '$$$$';
    let interpolated = [phrase];
    if (options == null)
        return interpolated;
    for (const arg of Object.keys(options)) {
        if (arg !== '_') {
            // Ensure replacement value is escaped to prevent special $-prefixed
            // regex replace tokens. the "$$$$" is needed because each "$" needs to
            // be escaped with "$" itself, and we need two in the resulting output.
            let replacement = options[arg];
            if (typeof replacement === 'string') {
                replacement = dollarRegex[Symbol.replace](replacement, dollarBillsYall);
            }
            // We create a new `RegExp` each time instead of using a more-efficient
            // string replace so that the same argument can be replaced multiple times
            // in the same phrase.
            interpolated = insertReplacement(interpolated, new RegExp(`%\\{${arg}\\}`, 'g'), replacement);
        }
    }
    return interpolated;
}
const defaultOnMissingKey = (key) => {
    throw new Error(`missing string: ${key}`);
};
/**
 * Translates strings with interpolation & pluralization support.
 * Extensible with custom dictionaries and pluralization functions.
 *
 * Borrows heavily from and inspired by Polyglot https://github.com/airbnb/polyglot.js,
 * basically a stripped-down version of it. Differences: pluralization functions are not hardcoded
 * and can be easily added among with dictionaries, nested objects are used for pluralization
 * as opposed to `||||` delimeter
 *
 * Usage example: `translator.translate('files_chosen', {smart_count: 3})`
 */
class Translator {
    locale;
    constructor(locales, { onMissingKey = defaultOnMissingKey } = {}) {
        this.locale = {
            strings: {},
            pluralize(n) {
                if (n === 1) {
                    return 0;
                }
                return 1;
            },
        };
        if (Array.isArray(locales)) {
            locales.forEach(this.#apply, this);
        }
        else {
            this.#apply(locales);
        }
        this.#onMissingKey = onMissingKey;
    }
    #onMissingKey;
    #apply(locale) {
        if (!locale?.strings) {
            return;
        }
        const prevLocale = this.locale;
        Object.assign(this.locale, {
            strings: { ...prevLocale.strings, ...locale.strings },
            pluralize: locale.pluralize || prevLocale.pluralize,
        });
    }
    /**
     * Public translate method
     *
     * @param key
     * @param options with values that will be used later to replace placeholders in string
     * @returns string translated (and interpolated)
     */
    translate(key, options) {
        return this.translateArray(key, options).join('');
    }
    /**
     * Get a translation and return the translated and interpolated parts as an array.
     *
     * @returns The translated and interpolated parts, in order.
     */
    translateArray(key, options) {
        let string = this.locale.strings[key];
        if (string == null) {
            this.#onMissingKey(key);
            string = key;
        }
        const hasPluralForms = typeof string === 'object';
        if (hasPluralForms) {
            if (options && typeof options.smart_count !== 'undefined') {
                const plural = this.locale.pluralize(options.smart_count);
                return interpolate(string[plural], options);
            }
            throw new Error('Attempted to use a string with plural forms, but no value was given for %{smart_count}');
        }
        if (typeof string !== 'string') {
            throw new Error(`string was not a string`);
        }
        return interpolate(string, options);
    }
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/fetcher.js"
/*!*************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/fetcher.js ***!
  \*************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   fetcher: () => (/* binding */ fetcher)
/* harmony export */ });
/* harmony import */ var _NetworkError_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./NetworkError.js */ "./node_modules/@uppy/utils/lib/NetworkError.js");
/* harmony import */ var _ProgressTimeout_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./ProgressTimeout.js */ "./node_modules/@uppy/utils/lib/ProgressTimeout.js");


const noop = () => { };
/**
 * Fetches data from a specified URL using XMLHttpRequest, with optional retry functionality and progress tracking.
 *
 * @param url The URL to send the request to.
 * @param options Optional settings for the fetch operation.
 */
function fetcher(url, options = {}) {
    const { body = null, headers = {}, method = 'GET', onBeforeRequest = noop, onUploadProgress = noop, shouldRetry = () => true, onAfterResponse = noop, onTimeout = noop, responseType, retries = 3, signal = null, timeout = 30_000, withCredentials = false, } = options;
    // 300 ms, 600 ms, 1200 ms, 2400 ms, 4800 ms
    const delay = (attempt) => 0.3 * 2 ** (attempt - 1) * 1000;
    const timer = new _ProgressTimeout_js__WEBPACK_IMPORTED_MODULE_1__["default"](timeout, onTimeout);
    function requestWithRetry(retryCount = 0) {
        // biome-ignore lint/suspicious/noAsyncPromiseExecutor: it's fine
        return new Promise(async (resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const onError = (error) => {
                if (shouldRetry(xhr) && retryCount < retries) {
                    setTimeout(() => {
                        requestWithRetry(retryCount + 1).then(resolve, reject);
                    }, delay(retryCount));
                }
                else {
                    timer.done();
                    reject(error);
                }
            };
            xhr.open(method, url, true);
            xhr.withCredentials = withCredentials;
            if (responseType) {
                xhr.responseType = responseType;
            }
            signal?.addEventListener('abort', () => {
                xhr.abort();
                // Using DOMException for abort errors aligns with
                // the convention established by the Fetch API.
                reject(new DOMException('Aborted', 'AbortError'));
            });
            xhr.onload = async () => {
                try {
                    await onAfterResponse(xhr, retryCount);
                }
                catch (err) {
                    // This is important as we need to emit the xhr
                    // over the upload-error event.
                    err.request = xhr;
                    onError(err);
                    return;
                }
                if (xhr.status >= 200 && xhr.status < 300) {
                    timer.done();
                    resolve(xhr);
                }
                else if (shouldRetry(xhr) && retryCount < retries) {
                    setTimeout(() => {
                        requestWithRetry(retryCount + 1).then(resolve, reject);
                    }, delay(retryCount));
                }
                else {
                    timer.done();
                    reject(new _NetworkError_js__WEBPACK_IMPORTED_MODULE_0__["default"](xhr.statusText, xhr));
                }
            };
            xhr.onerror = () => onError(new _NetworkError_js__WEBPACK_IMPORTED_MODULE_0__["default"](xhr.statusText, xhr));
            xhr.upload.onprogress = (event) => {
                timer.progress();
                onUploadProgress(event);
            };
            if (headers) {
                Object.keys(headers).forEach((key) => {
                    xhr.setRequestHeader(key, headers[key]);
                });
            }
            await onBeforeRequest(xhr, retryCount);
            xhr.send(body);
        });
    }
    return requestWithRetry();
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/fileFilters.js"
/*!*****************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/fileFilters.js ***!
  \*****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   filterFilesToEmitUploadStarted: () => (/* binding */ filterFilesToEmitUploadStarted),
/* harmony export */   filterNonFailedFiles: () => (/* binding */ filterNonFailedFiles)
/* harmony export */ });
function filterNonFailedFiles(files) {
    const hasError = (file) => 'error' in file && !!file.error;
    return files.filter((file) => !hasError(file));
}
// Don't double-emit upload-started for Golden Retriever-restored files that were already started
function filterFilesToEmitUploadStarted(files) {
    return files.filter((file) => !file.progress?.uploadStarted || !file.isRestored);
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/findDOMElement.js"
/*!********************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/findDOMElement.js ***!
  \********************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _isDOMElement_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./isDOMElement.js */ "./node_modules/@uppy/utils/lib/isDOMElement.js");

function findDOMElement(element, context = document) {
    if (typeof element === 'string') {
        return context.querySelector(element);
    }
    if ((0,_isDOMElement_js__WEBPACK_IMPORTED_MODULE_0__["default"])(element)) {
        return element;
    }
    return null;
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (findDOMElement);


/***/ },

/***/ "./node_modules/@uppy/utils/lib/generateFileID.js"
/*!********************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/generateFileID.js ***!
  \********************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ generateFileID),
/* harmony export */   getSafeFileId: () => (/* binding */ getSafeFileId)
/* harmony export */ });
/* harmony import */ var _getFileType_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./getFileType.js */ "./node_modules/@uppy/utils/lib/getFileType.js");

function encodeCharacter(character) {
    return character.charCodeAt(0).toString(32);
}
function encodeFilename(name) {
    let suffix = '';
    return (name.replace(/[^A-Z0-9]/gi, (character) => {
        suffix += `-${encodeCharacter(character)}`;
        return '/';
    }) + suffix);
}
/**
 * Takes a file object and turns it into fileID, by converting file.name to lowercase,
 * removing extra characters and adding type, size and lastModified
 */
function generateFileID(file, instanceId) {
    // It's tempting to do `[items].filter(Boolean).join('-')` here, but that
    // is slower! simple string concatenation is fast
    let id = instanceId || 'uppy';
    if (typeof file.name === 'string') {
        id += `-${encodeFilename(file.name.toLowerCase())}`;
    }
    if (file.type !== undefined) {
        id += `-${file.type}`;
    }
    if (file.meta && typeof file.meta.relativePath === 'string') {
        id += `-${encodeFilename(file.meta.relativePath.toLowerCase())}`;
    }
    if (file.data.size !== undefined) {
        id += `-${file.data.size}`;
    }
    if (file.data.lastModified !== undefined) {
        id += `-${file.data.lastModified}`;
    }
    return id;
}
// If the provider has a stable, unique ID, then we can use that to identify the file.
// Then we don't have to generate our own ID, and we can add the same file many times if needed (different path)
function hasFileStableId(file) {
    if (!file.isRemote || !file.remote)
        return false;
    // These are the providers that it seems like have stable IDs for their files. The other's I haven't checked yet.
    const stableIdProviders = new Set([
        'box',
        'dropbox',
        'drive',
        'facebook',
        'unsplash',
    ]);
    return stableIdProviders.has(file.remote.provider);
}
function getSafeFileId(file, instanceId) {
    if (hasFileStableId(file))
        return file.id;
    const fileType = (0,_getFileType_js__WEBPACK_IMPORTED_MODULE_0__["default"])(file);
    return generateFileID({
        ...file,
        type: fileType,
    }, instanceId);
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getAllowedMetaFields.js"
/*!**************************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getAllowedMetaFields.js ***!
  \**************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getAllowedMetaFields)
/* harmony export */ });
function getAllowedMetaFields(fields, meta) {
    if (fields === true) {
        return Object.keys(meta);
    }
    if (Array.isArray(fields)) {
        return fields;
    }
    return [];
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getDroppedFiles/index.js"
/*!***************************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getDroppedFiles/index.js ***!
  \***************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getDroppedFiles)
/* harmony export */ });
/* harmony import */ var _utils_fallbackApi_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils/fallbackApi.js */ "./node_modules/@uppy/utils/lib/getDroppedFiles/utils/fallbackApi.js");
/* harmony import */ var _utils_webkitGetAsEntryApi_index_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utils/webkitGetAsEntryApi/index.js */ "./node_modules/@uppy/utils/lib/getDroppedFiles/utils/webkitGetAsEntryApi/index.js");


/**
 * Returns a promise that resolves to the array of dropped files (if a folder is
 * dropped, and browser supports folder parsing - promise resolves to the flat
 * array of all files in all directories).
 * Each file has .relativePath prop appended to it (e.g. "/docs/Prague/ticket_from_prague_to_ufa.pdf")
 * if browser supports it. Otherwise it's undefined.
 *
 * @param dataTransfer
 * @param options
 * @param options.logDropError - a function that's called every time some
 * folder or some file error out (e.g. because of the folder name being too long
 * on Windows). Notice that resulting promise will always be resolved anyway.
 *
 * @returns {Promise} - Array<File>
 */
async function getDroppedFiles(dataTransfer, options) {
    // Get all files from all subdirs. Works (at least) in Chrome, Mozilla, and Safari
    const logDropError = options?.logDropError ?? Function.prototype;
    try {
        const accumulator = [];
        for await (const file of (0,_utils_webkitGetAsEntryApi_index_js__WEBPACK_IMPORTED_MODULE_1__["default"])(dataTransfer, logDropError)) {
            accumulator.push(file);
        }
        return accumulator;
        // Otherwise just return all first-order files
    }
    catch {
        return (0,_utils_fallbackApi_js__WEBPACK_IMPORTED_MODULE_0__["default"])(dataTransfer);
    }
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getDroppedFiles/utils/fallbackApi.js"
/*!***************************************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getDroppedFiles/utils/fallbackApi.js ***!
  \***************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ fallbackApi)
/* harmony export */ });
/* harmony import */ var _toArray_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../toArray.js */ "./node_modules/@uppy/utils/lib/toArray.js");

// .files fallback, should be implemented in any browser
function fallbackApi(dataTransfer) {
    const files = (0,_toArray_js__WEBPACK_IMPORTED_MODULE_0__["default"])(dataTransfer.files);
    return Promise.resolve(files);
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getDroppedFiles/utils/webkitGetAsEntryApi/getFilesAndDirectoriesFromDirectory.js"
/*!***********************************************************************************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getDroppedFiles/utils/webkitGetAsEntryApi/getFilesAndDirectoriesFromDirectory.js ***!
  \***********************************************************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getFilesAndDirectoriesFromDirectory)
/* harmony export */ });
/**
 * Recursive function, calls the original callback() when the directory is entirely parsed.
 */
function getFilesAndDirectoriesFromDirectory(directoryReader, oldEntries, logDropError, { onSuccess }) {
    directoryReader.readEntries((entries) => {
        const newEntries = [...oldEntries, ...entries];
        // According to the FileSystem API spec, getFilesAndDirectoriesFromDirectory()
        // must be called until it calls the onSuccess with an empty array.
        if (entries.length) {
            queueMicrotask(() => {
                getFilesAndDirectoriesFromDirectory(directoryReader, newEntries, logDropError, { onSuccess });
            });
            // Done iterating this particular directory
        }
        else {
            onSuccess(newEntries);
        }
    }, 
    // Make sure we resolve on error anyway, it's fine if only one directory couldn't be parsed!
    (error) => {
        logDropError(error);
        onSuccess(oldEntries);
    });
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getDroppedFiles/utils/webkitGetAsEntryApi/index.js"
/*!*****************************************************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getDroppedFiles/utils/webkitGetAsEntryApi/index.js ***!
  \*****************************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getFilesFromDataTransfer)
/* harmony export */ });
/* harmony import */ var _getFilesAndDirectoriesFromDirectory_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./getFilesAndDirectoriesFromDirectory.js */ "./node_modules/@uppy/utils/lib/getDroppedFiles/utils/webkitGetAsEntryApi/getFilesAndDirectoriesFromDirectory.js");

/**
 * Polyfill for the new (experimental) getAsFileSystemHandle API (using the popular webkitGetAsEntry behind the scenes)
 * so that we can switch to the getAsFileSystemHandle API once it (hopefully) becomes standard
 */
function getAsFileSystemHandleFromEntry(entry, logDropError) {
    if (entry == null)
        return entry;
    return {
        kind: entry.isFile
            ? 'file'
            : entry.isDirectory
                ? 'directory'
                : undefined,
        name: entry.name,
        getFile() {
            return new Promise((resolve, reject) => entry.file(resolve, reject));
        },
        async *values() {
            // If the file is a directory.
            const directoryReader = entry.createReader();
            const entries = await new Promise((resolve) => {
                (0,_getFilesAndDirectoriesFromDirectory_js__WEBPACK_IMPORTED_MODULE_0__["default"])(directoryReader, [], logDropError, {
                    onSuccess: (dirEntries) => resolve(dirEntries.map((file) => getAsFileSystemHandleFromEntry(file, logDropError))),
                });
            });
            yield* entries;
        },
        isSameEntry: undefined,
    };
}
async function* createPromiseToAddFileOrParseDirectory(entry, relativePath, lastResortFile = undefined) {
    const getNextRelativePath = () => `${relativePath}/${entry.name}`;
    // For each dropped item, - make sure it's a file/directory, and start deepening in!
    if (entry.kind === 'file') {
        const file = await entry.getFile();
        if (file != null) {
            ;
            file.relativePath = relativePath ? getNextRelativePath() : null;
            yield file;
        }
        else if (lastResortFile != null)
            yield lastResortFile;
    }
    else if (entry.kind === 'directory') {
        for await (const handle of entry.values()) {
            // Recurse on the directory, appending the dir name to the relative path
            yield* createPromiseToAddFileOrParseDirectory(handle, relativePath ? getNextRelativePath() : entry.name);
        }
    }
    else if (lastResortFile != null)
        yield lastResortFile;
}
/**
 * Load all files from data transfer, and recursively read any directories.
 * Note that IE is not supported for drag-drop, because IE doesn't support Data Transfers
 *
 * @param {DataTransfer} dataTransfer
 * @param {*} logDropError on error
 */
async function* getFilesFromDataTransfer(dataTransfer, logDropError) {
    // Retrieving the dropped items must happen synchronously
    // otherwise only the first item gets treated and the other ones are garbage collected.
    // https://github.com/transloadit/uppy/pull/3998
    const fileSystemHandles = await Promise.all(Array.from(dataTransfer.items, async (item) => {
        // biome-ignore lint/style/useConst: ...
        let fileSystemHandle;
        // TODO enable getAsFileSystemHandle API once we can get it working with subdirectories
        // IMPORTANT: Need to check isSecureContext *before* calling getAsFileSystemHandle
        // or else Chrome will crash when running in HTTP: https://github.com/transloadit/uppy/issues/4133
        // if (window.isSecureContext && item.getAsFileSystemHandle != null)
        // fileSystemHandle = await item.getAsFileSystemHandle()
        // `webkitGetAsEntry` exists in all popular browsers (including non-WebKit browsers),
        // however it may be renamed to getAsEntry() in the future, so you should code defensively, looking for both.
        // from https://developer.mozilla.org/en-US/docs/Web/API/DataTransferItem/webkitGetAsEntry
        const getAsEntry = () => typeof item.getAsEntry === 'function'
            ? item.getAsEntry()
            : item.webkitGetAsEntry();
        fileSystemHandle ??= getAsFileSystemHandleFromEntry(getAsEntry(), logDropError);
        return {
            fileSystemHandle,
            lastResortFile: item.getAsFile(), // can be used as a fallback in case other methods fail
        };
    }));
    for (const { lastResortFile, fileSystemHandle } of fileSystemHandles) {
        // fileSystemHandle and lastResortFile can be null when we drop an url.
        if (fileSystemHandle != null) {
            try {
                yield* createPromiseToAddFileOrParseDirectory(fileSystemHandle, '', lastResortFile);
            }
            catch (err) {
                // Example: If dropping a symbolic link, Chromium will throw:
                // "DOMException: A requested file or directory could not be found at the time an operation was processed.",
                // So we will use lastResortFile instead. See https://github.com/transloadit/uppy/issues/3505.
                if (lastResortFile != null) {
                    yield lastResortFile;
                }
                else {
                    logDropError(err);
                }
            }
        }
        else if (lastResortFile != null)
            yield lastResortFile;
    }
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getFileNameAndExtension.js"
/*!*****************************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getFileNameAndExtension.js ***!
  \*****************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getFileNameAndExtension)
/* harmony export */ });
/**
 * Takes a full filename string and returns an object {name, extension}
 */
function getFileNameAndExtension(fullFileName) {
    const lastDot = fullFileName.lastIndexOf('.');
    // these count as no extension: "no-dot", "trailing-dot."
    if (lastDot === -1 || lastDot === fullFileName.length - 1) {
        return {
            name: fullFileName,
            extension: undefined,
        };
    }
    return {
        name: fullFileName.slice(0, lastDot),
        extension: fullFileName.slice(lastDot + 1),
    };
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getFileType.js"
/*!*****************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getFileType.js ***!
  \*****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getFileType)
/* harmony export */ });
/* harmony import */ var _getFileNameAndExtension_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./getFileNameAndExtension.js */ "./node_modules/@uppy/utils/lib/getFileNameAndExtension.js");
/* harmony import */ var _mimeTypes_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./mimeTypes.js */ "./node_modules/@uppy/utils/lib/mimeTypes.js");


function getFileType(file) {
    if (file.type)
        return file.type;
    const fileExtension = file.name
        ? (0,_getFileNameAndExtension_js__WEBPACK_IMPORTED_MODULE_0__["default"])(file.name).extension?.toLowerCase()
        : null;
    if (fileExtension && fileExtension in _mimeTypes_js__WEBPACK_IMPORTED_MODULE_1__["default"]) {
        // else, see if we can map extension to a mime type
        return _mimeTypes_js__WEBPACK_IMPORTED_MODULE_1__["default"][fileExtension];
    }
    // if all fails, fall back to a generic byte stream type
    return 'application/octet-stream';
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getTextDirection.js"
/*!**********************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getTextDirection.js ***!
  \**********************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Get the declared text direction for an element.
 */
function getTextDirection(element) {
    // There is another way to determine text direction using getComputedStyle(), as done here:
    // https://github.com/pencil-js/text-direction/blob/2a235ce95089b3185acec3b51313cbba921b3811/text-direction.js
    //
    // We do not use that approach because we are interested specifically in the _declared_ text direction.
    // If no text direction is declared, we have to provide our own explicit text direction so our
    // bidirectional CSS style sheets work.
    while (element && !element.dir) {
        element = element.parentNode;
    }
    return element?.dir;
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (getTextDirection);


/***/ },

/***/ "./node_modules/@uppy/utils/lib/getTimeStamp.js"
/*!******************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/getTimeStamp.js ***!
  \******************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getTimeStamp)
/* harmony export */ });
/**
 * Adds zero to strings shorter than two characters.
 */
function pad(number) {
    return number < 10 ? `0${number}` : number.toString();
}
/**
 * Returns a timestamp in the format of `hours:minutes:seconds`
 */
function getTimeStamp() {
    const date = new Date();
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());
    const seconds = pad(date.getSeconds());
    return `${hours}:${minutes}:${seconds}`;
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/isDOMElement.js"
/*!******************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/isDOMElement.js ***!
  \******************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ isDOMElement)
/* harmony export */ });
/**
 * Check if an object is a DOM element. Duck-typing based on `nodeType`.
 */
function isDOMElement(obj) {
    if (typeof obj !== 'object' || obj === null)
        return false;
    if (!('nodeType' in obj))
        return false;
    return obj.nodeType === Node.ELEMENT_NODE;
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/isDragDropSupported.js"
/*!*************************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/isDragDropSupported.js ***!
  \*************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ isDragDropSupported)
/* harmony export */ });
/**
 * Checks if the browser supports Drag & Drop (not supported on mobile devices, for example).
 */
function isDragDropSupported() {
    const div = document.body;
    if (!('draggable' in div) || !('ondragstart' in div && 'ondrop' in div)) {
        return false;
    }
    if (!('FormData' in window)) {
        return false;
    }
    if (!('FileReader' in window)) {
        return false;
    }
    return true;
}


/***/ },

/***/ "./node_modules/@uppy/utils/lib/isNetworkError.js"
/*!********************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/isNetworkError.js ***!
  \********************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function isNetworkError(xhr) {
    if (!xhr) {
        return false;
    }
    return (xhr.readyState !== 0 && xhr.readyState !== 4) || xhr.status === 0;
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (isNetworkError);


/***/ },

/***/ "./node_modules/@uppy/utils/lib/mimeTypes.js"
/*!***************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/mimeTypes.js ***!
  \***************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
// ___Why not add the mime-types package?
//    It's 19.7kB gzipped, and we only need mime types for well-known extensions (for file previews).
// ___Where to take new extensions from?
//    https://github.com/jshttp/mime-db/blob/master/db.json
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
    __proto__: null,
    md: 'text/markdown',
    markdown: 'text/markdown',
    mp4: 'video/mp4',
    mp3: 'audio/mp3',
    svg: 'image/svg+xml',
    jpg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
    gif: 'image/gif',
    heic: 'image/heic',
    heif: 'image/heif',
    yaml: 'text/yaml',
    yml: 'text/yaml',
    csv: 'text/csv',
    tsv: 'text/tab-separated-values',
    tab: 'text/tab-separated-values',
    avi: 'video/x-msvideo',
    mks: 'video/x-matroska',
    mkv: 'video/x-matroska',
    mov: 'video/quicktime',
    dicom: 'application/dicom',
    doc: 'application/msword',
    msg: 'application/vnd.ms-outlook',
    docm: 'application/vnd.ms-word.document.macroenabled.12',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    dot: 'application/msword',
    dotm: 'application/vnd.ms-word.template.macroenabled.12',
    dotx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
    xla: 'application/vnd.ms-excel',
    xlam: 'application/vnd.ms-excel.addin.macroenabled.12',
    xlc: 'application/vnd.ms-excel',
    xlf: 'application/x-xliff+xml',
    xlm: 'application/vnd.ms-excel',
    xls: 'application/vnd.ms-excel',
    xlsb: 'application/vnd.ms-excel.sheet.binary.macroenabled.12',
    xlsm: 'application/vnd.ms-excel.sheet.macroenabled.12',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    xlt: 'application/vnd.ms-excel',
    xltm: 'application/vnd.ms-excel.template.macroenabled.12',
    xltx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
    xlw: 'application/vnd.ms-excel',
    txt: 'text/plain',
    text: 'text/plain',
    conf: 'text/plain',
    log: 'text/plain',
    pdf: 'application/pdf',
    zip: 'application/zip',
    '7z': 'application/x-7z-compressed',
    rar: 'application/x-rar-compressed',
    tar: 'application/x-tar',
    gz: 'application/gzip',
    dmg: 'application/x-apple-diskimage',
});


/***/ },

/***/ "./node_modules/@uppy/utils/lib/toArray.js"
/*!*************************************************!*\
  !*** ./node_modules/@uppy/utils/lib/toArray.js ***!
  \*************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Converts list into array
 */
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Array.from);


/***/ },

/***/ "./node_modules/@uppy/xhr-upload/lib/index.js"
/*!****************************************************!*\
  !*** ./node_modules/@uppy/xhr-upload/lib/index.js ***!
  \****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ XHRUpload)
/* harmony export */ });
/* harmony import */ var _uppy_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @uppy/core */ "./node_modules/@uppy/core/lib/BasePlugin.js");
/* harmony import */ var _uppy_core_lib_EventManager_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @uppy/core/lib/EventManager.js */ "./node_modules/@uppy/core/lib/EventManager.js");
/* harmony import */ var _uppy_utils_lib_fetcher__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @uppy/utils/lib/fetcher */ "./node_modules/@uppy/utils/lib/fetcher.js");
/* harmony import */ var _uppy_utils_lib_fileFilters__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @uppy/utils/lib/fileFilters */ "./node_modules/@uppy/utils/lib/fileFilters.js");
/* harmony import */ var _uppy_utils_lib_getAllowedMetaFields__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @uppy/utils/lib/getAllowedMetaFields */ "./node_modules/@uppy/utils/lib/getAllowedMetaFields.js");
/* harmony import */ var _uppy_utils_lib_isNetworkError__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @uppy/utils/lib/isNetworkError */ "./node_modules/@uppy/utils/lib/isNetworkError.js");
/* harmony import */ var _uppy_utils_lib_NetworkError__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @uppy/utils/lib/NetworkError */ "./node_modules/@uppy/utils/lib/NetworkError.js");
/* harmony import */ var _uppy_utils_lib_RateLimitedQueue__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @uppy/utils/lib/RateLimitedQueue */ "./node_modules/@uppy/utils/lib/RateLimitedQueue.js");
/* harmony import */ var _package_json__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../package.json */ "./node_modules/@uppy/xhr-upload/package.json");
/* harmony import */ var _locale_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./locale.js */ "./node_modules/@uppy/xhr-upload/lib/locale.js");










function buildResponseError(xhr, err) {
    let error = err;
    // No error message
    if (!error)
        error = new Error('Upload error');
    // Got an error message string
    if (typeof error === 'string')
        error = new Error(error);
    // Got something else
    if (!(error instanceof Error)) {
        error = Object.assign(new Error('Upload error'), { data: error });
    }
    if ((0,_uppy_utils_lib_isNetworkError__WEBPACK_IMPORTED_MODULE_5__["default"])(xhr)) {
        error = new _uppy_utils_lib_NetworkError__WEBPACK_IMPORTED_MODULE_6__["default"](error, xhr);
        return error;
    }
    // @ts-expect-error request can only be set on NetworkError
    // but we use NetworkError to distinguish between errors.
    error.request = xhr;
    return error;
}
/**
 * Set `data.type` in the blob to `file.meta.type`,
 * because we might have detected a more accurate file type in Uppy
 * https://stackoverflow.com/a/50875615
 */
function setTypeInBlob(file) {
    const dataWithUpdatedType = file.data.slice(0, file.data.size, file.meta.type);
    return dataWithUpdatedType;
}
const defaultOptions = {
    formData: true,
    fieldName: 'file',
    method: 'post',
    allowedMetaFields: true,
    bundle: false,
    headers: {},
    timeout: 30 * 1000,
    limit: 5,
    withCredentials: false,
    responseType: '',
};
class XHRUpload extends _uppy_core__WEBPACK_IMPORTED_MODULE_0__["default"] {
    static VERSION = _package_json__WEBPACK_IMPORTED_MODULE_8__.version;
    #getFetcher;
    requests;
    uploaderEvents;
    constructor(uppy, opts) {
        super(uppy, {
            ...defaultOptions,
            fieldName: opts.bundle ? 'files[]' : 'file',
            ...opts,
        });
        this.type = 'uploader';
        this.id = this.opts.id || 'XHRUpload';
        this.defaultLocale = _locale_js__WEBPACK_IMPORTED_MODULE_9__["default"];
        this.i18nInit();
        // Simultaneous upload limiting is shared across all uploads with this plugin.
        if (_uppy_utils_lib_RateLimitedQueue__WEBPACK_IMPORTED_MODULE_7__.internalRateLimitedQueue in this.opts) {
            // @ts-ignore untyped internal
            this.requests = this.opts[_uppy_utils_lib_RateLimitedQueue__WEBPACK_IMPORTED_MODULE_7__.internalRateLimitedQueue];
        }
        else {
            this.requests = new _uppy_utils_lib_RateLimitedQueue__WEBPACK_IMPORTED_MODULE_7__.RateLimitedQueue(this.opts.limit);
        }
        if (this.opts.bundle && !this.opts.formData) {
            throw new Error('`opts.formData` must be true when `opts.bundle` is enabled.');
        }
        if (this.opts.bundle && typeof this.opts.headers === 'function') {
            throw new Error('`opts.headers` can not be a function when the `bundle: true` option is set.');
        }
        if (opts?.allowedMetaFields === undefined && 'metaFields' in this.opts) {
            throw new Error('The `metaFields` option has been renamed to `allowedMetaFields`.');
        }
        this.uploaderEvents = Object.create(null);
        /**
         * xhr-upload wrapper for `fetcher` to handle user options
         * `validateStatus`, `getResponseError`, `getResponseData`
         * and to emit `upload-progress`, `upload-error`, and `upload-success` events.
         */
        this.#getFetcher = (files) => {
            return async (url, options) => {
                try {
                    const res = await (0,_uppy_utils_lib_fetcher__WEBPACK_IMPORTED_MODULE_2__.fetcher)(url, {
                        ...options,
                        onBeforeRequest: (xhr, retryCount) => this.opts.onBeforeRequest?.(xhr, retryCount, files),
                        shouldRetry: this.opts.shouldRetry,
                        onAfterResponse: this.opts.onAfterResponse,
                        onTimeout: (timeout) => {
                            const seconds = Math.ceil(timeout / 1000);
                            const error = new Error(this.i18n('uploadStalled', { seconds }));
                            this.uppy.emit('upload-stalled', error, files);
                        },
                        onUploadProgress: (event) => {
                            if (event.lengthComputable) {
                                for (const { id } of files) {
                                    const file = this.uppy.getFile(id);
                                    this.uppy.emit('upload-progress', file, {
                                        uploadStarted: file.progress.uploadStarted ?? 0,
                                        bytesUploaded: (event.loaded / event.total) * file.size,
                                        bytesTotal: file.size,
                                    });
                                }
                            }
                        },
                    });
                    let body = await this.opts.getResponseData?.(res);
                    if (res.responseType === 'json') {
                        body ??= res.response;
                    }
                    else {
                        try {
                            body ??= JSON.parse(res.responseText);
                        }
                        catch (cause) {
                            throw new Error('@uppy/xhr-upload expects a JSON response (with a `url` property). To parse non-JSON responses, use `getResponseData` to turn your response into JSON.', { cause });
                        }
                    }
                    const uploadURL = typeof body?.url === 'string' ? body.url : undefined;
                    for (const { id } of files) {
                        this.uppy.emit('upload-success', this.uppy.getFile(id), {
                            status: res.status,
                            body,
                            uploadURL,
                        });
                    }
                    return res;
                }
                catch (error) {
                    if (error.name === 'AbortError') {
                        return undefined;
                    }
                    const request = error.request;
                    for (const file of files) {
                        this.uppy.emit('upload-error', this.uppy.getFile(file.id), buildResponseError(request, error), request);
                    }
                    throw error;
                }
            };
        };
    }
    getOptions(file) {
        const overrides = this.uppy.getState().xhrUpload;
        const { headers } = this.opts;
        const opts = {
            ...this.opts,
            ...(overrides || {}),
            ...(file.xhrUpload || {}),
            headers: {},
        };
        // Support for `headers` as a function, only in the XHRUpload settings.
        // Options set by other plugins in Uppy state or on the files themselves are still merged in afterward.
        //
        // ```js
        // headers: (file) => ({ expires: file.meta.expires })
        // ```
        if (typeof headers === 'function') {
            opts.headers = headers(file);
        }
        else {
            Object.assign(opts.headers, this.opts.headers);
        }
        if (overrides) {
            Object.assign(opts.headers, overrides.headers);
        }
        if (file.xhrUpload) {
            Object.assign(opts.headers, file.xhrUpload.headers);
        }
        return opts;
    }
    addMetadata(formData, meta, opts) {
        const allowedMetaFields = (0,_uppy_utils_lib_getAllowedMetaFields__WEBPACK_IMPORTED_MODULE_4__["default"])(opts.allowedMetaFields, meta);
        allowedMetaFields.forEach((item) => {
            const value = meta[item];
            if (Array.isArray(value)) {
                // In this case we don't transform `item` to add brackets, it's up to
                // the user to add the brackets so it won't be overridden.
                value.forEach((subItem) => formData.append(item, subItem));
            }
            else {
                formData.append(item, value);
            }
        });
    }
    createFormDataUpload(file, opts) {
        const formPost = new FormData();
        this.addMetadata(formPost, file.meta, opts);
        const dataWithUpdatedType = setTypeInBlob(file);
        if (file.name) {
            formPost.append(opts.fieldName, dataWithUpdatedType, file.meta.name);
        }
        else {
            formPost.append(opts.fieldName, dataWithUpdatedType);
        }
        return formPost;
    }
    createBundledUpload(files, opts) {
        const formPost = new FormData();
        const { meta } = this.uppy.getState();
        this.addMetadata(formPost, meta, opts);
        files.forEach((file) => {
            const options = this.getOptions(file);
            const dataWithUpdatedType = setTypeInBlob(file);
            if (file.name) {
                formPost.append(options.fieldName, dataWithUpdatedType, file.name);
            }
            else {
                formPost.append(options.fieldName, dataWithUpdatedType);
            }
        });
        return formPost;
    }
    async #uploadLocalFile(file) {
        const events = new _uppy_core_lib_EventManager_js__WEBPACK_IMPORTED_MODULE_1__["default"](this.uppy);
        const controller = new AbortController();
        const uppyFetch = this.requests.wrapPromiseFunction(async () => {
            const opts = this.getOptions(file);
            const fetch = this.#getFetcher([file]);
            const body = opts.formData
                ? this.createFormDataUpload(file, opts)
                : file.data;
            const endpoint = typeof opts.endpoint === 'string'
                ? opts.endpoint
                : await opts.endpoint(file);
            return fetch(endpoint, {
                ...opts,
                body,
                signal: controller.signal,
            });
        });
        events.onFileRemove(file.id, () => controller.abort());
        events.onCancelAll(file.id, () => {
            controller.abort();
        });
        try {
            await uppyFetch().abortOn(controller.signal);
        }
        catch (error) {
            // TODO: create formal error with name 'AbortError' (this comes from RateLimitedQueue)
            if (error.message !== 'Cancelled') {
                throw error;
            }
        }
        finally {
            events.remove();
        }
    }
    async #uploadBundle(files) {
        const controller = new AbortController();
        const uppyFetch = this.requests.wrapPromiseFunction(async () => {
            const optsFromState = this.uppy.getState().xhrUpload ?? {};
            const fetch = this.#getFetcher(files);
            const body = this.createBundledUpload(files, {
                ...this.opts,
                ...optsFromState,
            });
            const endpoint = typeof this.opts.endpoint === 'string'
                ? this.opts.endpoint
                : await this.opts.endpoint(files);
            return fetch(endpoint, {
                // headers can't be a function with bundle: true
                ...this.opts,
                body,
                signal: controller.signal,
            });
        });
        function abort() {
            controller.abort();
        }
        // We only need to abort on cancel all because
        // individual cancellations are not possible with bundle: true
        this.uppy.once('cancel-all', abort);
        try {
            await uppyFetch().abortOn(controller.signal);
        }
        catch (error) {
            // TODO: create formal error with name 'AbortError' (this comes from RateLimitedQueue)
            if (error.message !== 'Cancelled') {
                throw error;
            }
        }
        finally {
            this.uppy.off('cancel-all', abort);
        }
    }
    #getCompanionClientArgs(file) {
        const opts = this.getOptions(file);
        const allowedMetaFields = (0,_uppy_utils_lib_getAllowedMetaFields__WEBPACK_IMPORTED_MODULE_4__["default"])(opts.allowedMetaFields, file.meta);
        return {
            ...file.remote?.body,
            protocol: 'multipart',
            endpoint: opts.endpoint,
            size: file.data.size,
            fieldname: opts.fieldName,
            metadata: Object.fromEntries(allowedMetaFields.map((name) => [name, file.meta[name]])),
            httpMethod: opts.method,
            useFormData: opts.formData,
            headers: opts.headers,
        };
    }
    async #uploadFiles(files) {
        await Promise.allSettled(files.map((file) => {
            if (file.isRemote) {
                const getQueue = () => this.requests;
                const controller = new AbortController();
                const removedHandler = (removedFile) => {
                    if (removedFile.id === file.id)
                        controller.abort();
                };
                this.uppy.on('file-removed', removedHandler);
                const uploadPromise = this.uppy
                    .getRequestClientForFile(file)
                    .uploadRemoteFile(file, this.#getCompanionClientArgs(file), {
                    signal: controller.signal,
                    getQueue,
                });
                this.requests.wrapSyncFunction(() => {
                    this.uppy.off('file-removed', removedHandler);
                }, { priority: -1 })();
                return uploadPromise;
            }
            return this.#uploadLocalFile(file);
        }));
    }
    #handleUpload = async (fileIDs) => {
        if (fileIDs.length === 0) {
            this.uppy.log('[XHRUpload] No files to upload!');
            return;
        }
        // No limit configured by the user, and no RateLimitedQueue passed in by a "parent" plugin
        // (basically just AwsS3) using the internal symbol
        // @ts-ignore untyped internal
        if (this.opts.limit === 0 && !this.opts[_uppy_utils_lib_RateLimitedQueue__WEBPACK_IMPORTED_MODULE_7__.internalRateLimitedQueue]) {
            this.uppy.log('[XHRUpload] When uploading multiple files at once, consider setting the `limit` option (to `10` for example), to limit the number of concurrent uploads, which helps prevent memory and network issues: https://uppy.io/docs/xhr-upload/#limit-0', 'warning');
        }
        this.uppy.log('[XHRUpload] Uploading...');
        const files = this.uppy.getFilesByIds(fileIDs);
        const filesFiltered = (0,_uppy_utils_lib_fileFilters__WEBPACK_IMPORTED_MODULE_3__.filterNonFailedFiles)(files);
        const filesToEmit = (0,_uppy_utils_lib_fileFilters__WEBPACK_IMPORTED_MODULE_3__.filterFilesToEmitUploadStarted)(filesFiltered);
        this.uppy.emit('upload-start', filesToEmit);
        if (this.opts.bundle) {
            // if bundle: true, we don’t support remote uploads
            const isSomeFileRemote = filesFiltered.some((file) => file.isRemote);
            if (isSomeFileRemote) {
                throw new Error('Can’t upload remote files when the `bundle: true` option is set');
            }
            if (typeof this.opts.headers === 'function') {
                throw new TypeError('`headers` may not be a function when the `bundle: true` option is set');
            }
            await this.#uploadBundle(filesFiltered);
        }
        else {
            await this.#uploadFiles(filesFiltered);
        }
    };
    install() {
        if (this.opts.bundle) {
            const { capabilities } = this.uppy.getState();
            this.uppy.setState({
                capabilities: {
                    ...capabilities,
                    individualCancellation: false,
                },
            });
        }
        this.uppy.addUploader(this.#handleUpload);
    }
    uninstall() {
        if (this.opts.bundle) {
            const { capabilities } = this.uppy.getState();
            this.uppy.setState({
                capabilities: {
                    ...capabilities,
                    individualCancellation: true,
                },
            });
        }
        this.uppy.removeUploader(this.#handleUpload);
    }
}


/***/ },

/***/ "./node_modules/@uppy/xhr-upload/lib/locale.js"
/*!*****************************************************!*\
  !*** ./node_modules/@uppy/xhr-upload/lib/locale.js ***!
  \*****************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
    strings: {
        // Shown in the Informer if an upload is being canceled because it stalled for too long.
        uploadStalled: 'Upload has not made any progress for %{seconds} seconds. You may want to retry it.',
    },
});


/***/ },

/***/ "./node_modules/fabric/dist/index.min.mjs"
/*!************************************************!*\
  !*** ./node_modules/fabric/dist/index.min.mjs ***!
  \************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ActiveSelection: () => (/* binding */ Qo),
/* harmony export */   BaseBrush: () => (/* binding */ so),
/* harmony export */   BaseFabricObject: () => (/* binding */ ei),
/* harmony export */   Canvas: () => (/* binding */ Xn),
/* harmony export */   Canvas2dFilterBackend: () => (/* binding */ Zo),
/* harmony export */   CanvasDOMManager: () => (/* binding */ Pn),
/* harmony export */   Circle: () => (/* binding */ co),
/* harmony export */   CircleBrush: () => (/* binding */ lo),
/* harmony export */   ClipPathLayout: () => (/* binding */ qo),
/* harmony export */   Color: () => (/* binding */ Ie),
/* harmony export */   Control: () => (/* binding */ ai),
/* harmony export */   Ellipse: () => (/* binding */ xo),
/* harmony export */   FabricImage: () => (/* binding */ oa),
/* harmony export */   FabricObject: () => (/* binding */ Li),
/* harmony export */   FabricText: () => (/* binding */ Ao),
/* harmony export */   FitContentLayout: () => (/* binding */ Yr),
/* harmony export */   FixedLayout: () => (/* binding */ Ko),
/* harmony export */   Gradient: () => (/* binding */ $n),
/* harmony export */   Group: () => (/* binding */ Ur),
/* harmony export */   IText: () => (/* binding */ No),
/* harmony export */   Image: () => (/* binding */ oa),
/* harmony export */   InteractiveFabricObject: () => (/* binding */ ji),
/* harmony export */   Intersection: () => (/* binding */ Qs),
/* harmony export */   LayoutManager: () => (/* binding */ Gr),
/* harmony export */   LayoutStrategy: () => (/* binding */ Xr),
/* harmony export */   Line: () => (/* binding */ vo),
/* harmony export */   Object: () => (/* binding */ Li),
/* harmony export */   Observable: () => (/* binding */ st),
/* harmony export */   Path: () => (/* binding */ no),
/* harmony export */   Pattern: () => (/* binding */ eo),
/* harmony export */   PatternBrush: () => (/* binding */ go),
/* harmony export */   PencilBrush: () => (/* binding */ oo),
/* harmony export */   Point: () => (/* binding */ ot),
/* harmony export */   Polygon: () => (/* binding */ To),
/* harmony export */   Polyline: () => (/* binding */ wo),
/* harmony export */   Rect: () => (/* binding */ jr),
/* harmony export */   Shadow: () => (/* binding */ Ds),
/* harmony export */   SprayBrush: () => (/* binding */ uo),
/* harmony export */   StaticCanvas: () => (/* binding */ ie),
/* harmony export */   StaticCanvasDOMManager: () => (/* binding */ te),
/* harmony export */   Text: () => (/* binding */ Ao),
/* harmony export */   Textbox: () => (/* binding */ Uo),
/* harmony export */   Triangle: () => (/* binding */ yo),
/* harmony export */   WebGLFilterBackend: () => (/* binding */ $o),
/* harmony export */   cache: () => (/* binding */ _),
/* harmony export */   classRegistry: () => (/* binding */ tt),
/* harmony export */   config: () => (/* binding */ o),
/* harmony export */   controlsUtils: () => (/* binding */ Fa),
/* harmony export */   createCollectionMixin: () => (/* binding */ ct),
/* harmony export */   filters: () => (/* binding */ Sh),
/* harmony export */   getCSSRules: () => (/* binding */ pa),
/* harmony export */   getEnv: () => (/* binding */ p),
/* harmony export */   getFabricDocument: () => (/* binding */ m),
/* harmony export */   getFabricWindow: () => (/* binding */ v),
/* harmony export */   getFilterBackend: () => (/* binding */ sa),
/* harmony export */   iMatrix: () => (/* binding */ T),
/* harmony export */   initFilterBackend: () => (/* binding */ ea),
/* harmony export */   isPutImageFaster: () => (/* binding */ Ra),
/* harmony export */   isWebGLPipelineState: () => (/* binding */ La),
/* harmony export */   loadSVGFromString: () => (/* binding */ Ca),
/* harmony export */   loadSVGFromURL: () => (/* binding */ ba),
/* harmony export */   parseAttributes: () => (/* binding */ Pr),
/* harmony export */   parseFontDeclaration: () => (/* binding */ Or),
/* harmony export */   parsePointsAttribute: () => (/* binding */ Co),
/* harmony export */   parseSVGDocument: () => (/* binding */ xa),
/* harmony export */   parseStyleAttribute: () => (/* binding */ Dr),
/* harmony export */   parseTransformAttribute: () => (/* binding */ wr),
/* harmony export */   runningAnimations: () => (/* binding */ et),
/* harmony export */   setEnv: () => (/* binding */ f),
/* harmony export */   setFilterBackend: () => (/* binding */ ia),
/* harmony export */   util: () => (/* binding */ Mn),
/* harmony export */   version: () => (/* binding */ x)
/* harmony export */ });
function t(t,e,s){return(e=function(t){var e=function(t,e){if("object"!=typeof t||!t)return t;var s=t[Symbol.toPrimitive];if(void 0!==s){var i=s.call(t,e||"default");if("object"!=typeof i)return i;throw new TypeError("@@toPrimitive must return a primitive value.")}return("string"===e?String:Number)(t)}(t,"string");return"symbol"==typeof e?e:e+""}(e))in t?Object.defineProperty(t,e,{value:s,enumerable:!0,configurable:!0,writable:!0}):t[e]=s,t}function e(t,e){var s=Object.keys(t);if(Object.getOwnPropertySymbols){var i=Object.getOwnPropertySymbols(t);e&&(i=i.filter((function(e){return Object.getOwnPropertyDescriptor(t,e).enumerable}))),s.push.apply(s,i)}return s}function s(s){for(var i=1;i<arguments.length;i++){var r=null!=arguments[i]?arguments[i]:{};i%2?e(Object(r),!0).forEach((function(e){t(s,e,r[e])})):Object.getOwnPropertyDescriptors?Object.defineProperties(s,Object.getOwnPropertyDescriptors(r)):e(Object(r)).forEach((function(t){Object.defineProperty(s,t,Object.getOwnPropertyDescriptor(r,t))}))}return s}function i(t,e){if(null==t)return{};var s,i,r=function(t,e){if(null==t)return{};var s={};for(var i in t)if({}.hasOwnProperty.call(t,i)){if(e.indexOf(i)>=0)continue;s[i]=t[i]}return s}(t,e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);for(i=0;i<n.length;i++)s=n[i],e.indexOf(s)>=0||{}.propertyIsEnumerable.call(t,s)&&(r[s]=t[s])}return r}function r(t,e){return e||(e=t.slice(0)),Object.freeze(Object.defineProperties(t,{raw:{value:Object.freeze(e)}}))}class n{constructor(){t(this,"browserShadowBlurConstant",1),t(this,"DPI",96),t(this,"devicePixelRatio","undefined"!=typeof window?window.devicePixelRatio:1),t(this,"perfLimitSizeTotal",2097152),t(this,"maxCacheSideLimit",4096),t(this,"minCacheSideLimit",256),t(this,"disableStyleCopyPaste",!1),t(this,"enableGLFiltering",!0),t(this,"textureSize",4096),t(this,"forceGLPutImageData",!1),t(this,"cachesBoundsOfCurve",!1),t(this,"fontPaths",{}),t(this,"NUM_FRACTION_DIGITS",4)}}const o=new class extends n{constructor(t){super(),this.configure(t)}configure(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};Object.assign(this,t)}addFonts(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};this.fontPaths=s(s({},this.fontPaths),t)}removeFonts(){(arguments.length>0&&void 0!==arguments[0]?arguments[0]:[]).forEach((t=>{delete this.fontPaths[t]}))}clearFonts(){this.fontPaths={}}restoreDefaults(t){const e=new n,s=(null==t?void 0:t.reduce(((t,s)=>(t[s]=e[s],t)),{}))||e;this.configure(s)}},a=function(t){for(var e=arguments.length,s=new Array(e>1?e-1:0),i=1;i<e;i++)s[i-1]=arguments[i];return console[t]("fabric",...s)};class h extends Error{constructor(t,e){super("fabric: ".concat(t),e)}}class c extends h{constructor(t){super("".concat(t," 'options.signal' is in 'aborted' state"))}}class l{}class u extends l{testPrecision(t,e){const s="precision ".concat(e," float;\nvoid main(){}"),i=t.createShader(t.FRAGMENT_SHADER);return!!i&&(t.shaderSource(i,s),t.compileShader(i),!!t.getShaderParameter(i,t.COMPILE_STATUS))}queryWebGL(t){const e=t.getContext("webgl");e&&(this.maxTextureSize=e.getParameter(e.MAX_TEXTURE_SIZE),this.GLPrecision=["highp","mediump","lowp"].find((t=>this.testPrecision(e,t))),e.getExtension("WEBGL_lose_context").loseContext(),a("log","WebGL: max texture size ".concat(this.maxTextureSize)))}isSupported(t){return!!this.maxTextureSize&&this.maxTextureSize>=t}}const d={};let g;const f=t=>{g=t},p=()=>g||(g={document:document,window:window,isTouchSupported:"ontouchstart"in window||"ontouchstart"in document||window&&window.navigator&&window.navigator.maxTouchPoints>0,WebGLProbe:new u,dispose(){},copyPasteData:d}),m=()=>p().document,v=()=>p().window,y=()=>{var t;return Math.max(null!==(t=o.devicePixelRatio)&&void 0!==t?t:v().devicePixelRatio,1)};const _=new class{constructor(){t(this,"boundsOfCurveCache",{}),this.charWidthsCache=new Map}getFontCache(t){let{fontFamily:e,fontStyle:s,fontWeight:i}=t;e=e.toLowerCase();const r=this.charWidthsCache;r.has(e)||r.set(e,new Map);const n=r.get(e),o="".concat(s.toLowerCase(),"_").concat((i+"").toLowerCase());return n.has(o)||n.set(o,new Map),n.get(o)}clearFontCache(t){t?this.charWidthsCache.delete((t||"").toLowerCase()):this.charWidthsCache=new Map}limitDimsByArea(t){const{perfLimitSizeTotal:e}=o,s=Math.sqrt(e*t);return[Math.floor(s),Math.floor(e/s)]}};const x="6.9.1";function C(){}const b=Math.PI/2,S=2*Math.PI,w=Math.PI/180,T=Object.freeze([1,0,0,1,0,0]),O=16,k=.4477152502,D="center",M="left",P="top",E="bottom",A="right",j="none",F=/\r?\n/,L="moving",R="scaling",I="rotating",B="rotate",X="skewing",Y="resizing",W="modifyPoly",V="modifyPath",z="changed",G="scale",H="scaleX",N="scaleY",U="skewX",q="skewY",K="fill",J="stroke",Q="modified",Z="json",$="svg";const tt=new class{constructor(){this[Z]=new Map,this[$]=new Map}has(t){return this[Z].has(t)}getClass(t){const e=this[Z].get(t);if(!e)throw new h("No class registered for ".concat(t));return e}setClass(t,e){e?this[Z].set(e,t):(this[Z].set(t.type,t),this[Z].set(t.type.toLowerCase(),t))}getSVGClass(t){return this[$].get(t)}setSVGClass(t,e){this[$].set(null!=e?e:t.type.toLowerCase(),t)}};const et=new class extends Array{remove(t){const e=this.indexOf(t);e>-1&&this.splice(e,1)}cancelAll(){const t=this.splice(0);return t.forEach((t=>t.abort())),t}cancelByCanvas(t){if(!t)return[];const e=this.filter((e=>{var s;return e.target===t||"object"==typeof e.target&&(null===(s=e.target)||void 0===s?void 0:s.canvas)===t}));return e.forEach((t=>t.abort())),e}cancelByTarget(t){if(!t)return[];const e=this.filter((e=>e.target===t));return e.forEach((t=>t.abort())),e}};class st{constructor(){t(this,"__eventListeners",{})}on(t,e){if(this.__eventListeners||(this.__eventListeners={}),"object"==typeof t)return Object.entries(t).forEach((t=>{let[e,s]=t;this.on(e,s)})),()=>this.off(t);if(e){const s=t;return this.__eventListeners[s]||(this.__eventListeners[s]=[]),this.__eventListeners[s].push(e),()=>this.off(s,e)}return()=>!1}once(t,e){if("object"==typeof t){const e=[];return Object.entries(t).forEach((t=>{let[s,i]=t;e.push(this.once(s,i))})),()=>e.forEach((t=>t()))}if(e){const s=this.on(t,(function(){for(var t=arguments.length,i=new Array(t),r=0;r<t;r++)i[r]=arguments[r];e.call(this,...i),s()}));return s}return()=>!1}_removeEventListener(t,e){if(this.__eventListeners[t])if(e){const s=this.__eventListeners[t],i=s.indexOf(e);i>-1&&s.splice(i,1)}else this.__eventListeners[t]=[]}off(t,e){if(this.__eventListeners)if(void 0===t)for(const t in this.__eventListeners)this._removeEventListener(t);else"object"==typeof t?Object.entries(t).forEach((t=>{let[e,s]=t;this._removeEventListener(e,s)})):this._removeEventListener(t,e)}fire(t,e){var s;if(!this.__eventListeners)return;const i=null===(s=this.__eventListeners[t])||void 0===s?void 0:s.concat();if(i)for(let t=0;t<i.length;t++)i[t].call(this,e||{})}}const it=(t,e)=>{const s=t.indexOf(e);return-1!==s&&t.splice(s,1),t},rt=t=>{if(0===t)return 1;switch(Math.abs(t)/b){case 1:case 3:return 0;case 2:return-1}return Math.cos(t)},nt=t=>{if(0===t)return 0;const e=t/b,s=Math.sign(t);switch(e){case 1:return s;case 2:return 0;case 3:return-s}return Math.sin(t)};class ot{constructor(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:0,e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0;"object"==typeof t?(this.x=t.x,this.y=t.y):(this.x=t,this.y=e)}add(t){return new ot(this.x+t.x,this.y+t.y)}addEquals(t){return this.x+=t.x,this.y+=t.y,this}scalarAdd(t){return new ot(this.x+t,this.y+t)}scalarAddEquals(t){return this.x+=t,this.y+=t,this}subtract(t){return new ot(this.x-t.x,this.y-t.y)}subtractEquals(t){return this.x-=t.x,this.y-=t.y,this}scalarSubtract(t){return new ot(this.x-t,this.y-t)}scalarSubtractEquals(t){return this.x-=t,this.y-=t,this}multiply(t){return new ot(this.x*t.x,this.y*t.y)}scalarMultiply(t){return new ot(this.x*t,this.y*t)}scalarMultiplyEquals(t){return this.x*=t,this.y*=t,this}divide(t){return new ot(this.x/t.x,this.y/t.y)}scalarDivide(t){return new ot(this.x/t,this.y/t)}scalarDivideEquals(t){return this.x/=t,this.y/=t,this}eq(t){return this.x===t.x&&this.y===t.y}lt(t){return this.x<t.x&&this.y<t.y}lte(t){return this.x<=t.x&&this.y<=t.y}gt(t){return this.x>t.x&&this.y>t.y}gte(t){return this.x>=t.x&&this.y>=t.y}lerp(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:.5;return e=Math.max(Math.min(1,e),0),new ot(this.x+(t.x-this.x)*e,this.y+(t.y-this.y)*e)}distanceFrom(t){const e=this.x-t.x,s=this.y-t.y;return Math.sqrt(e*e+s*s)}midPointFrom(t){return this.lerp(t)}min(t){return new ot(Math.min(this.x,t.x),Math.min(this.y,t.y))}max(t){return new ot(Math.max(this.x,t.x),Math.max(this.y,t.y))}toString(){return"".concat(this.x,",").concat(this.y)}setXY(t,e){return this.x=t,this.y=e,this}setX(t){return this.x=t,this}setY(t){return this.y=t,this}setFromPoint(t){return this.x=t.x,this.y=t.y,this}swap(t){const e=this.x,s=this.y;this.x=t.x,this.y=t.y,t.x=e,t.y=s}clone(){return new ot(this.x,this.y)}rotate(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:at;const s=nt(t),i=rt(t),r=this.subtract(e);return new ot(r.x*i-r.y*s,r.x*s+r.y*i).add(e)}transform(t){let e=arguments.length>1&&void 0!==arguments[1]&&arguments[1];return new ot(t[0]*this.x+t[2]*this.y+(e?0:t[4]),t[1]*this.x+t[3]*this.y+(e?0:t[5]))}}const at=new ot(0,0),ht=t=>!!t&&Array.isArray(t._objects);function ct(e){class s extends e{constructor(){super(...arguments),t(this,"_objects",[])}_onObjectAdded(t){}_onObjectRemoved(t){}_onStackOrderChanged(t){}add(){for(var t=arguments.length,e=new Array(t),s=0;s<t;s++)e[s]=arguments[s];const i=this._objects.push(...e);return e.forEach((t=>this._onObjectAdded(t))),i}insertAt(t){for(var e=arguments.length,s=new Array(e>1?e-1:0),i=1;i<e;i++)s[i-1]=arguments[i];return this._objects.splice(t,0,...s),s.forEach((t=>this._onObjectAdded(t))),this._objects.length}remove(){const t=this._objects,e=[];for(var s=arguments.length,i=new Array(s),r=0;r<s;r++)i[r]=arguments[r];return i.forEach((s=>{const i=t.indexOf(s);-1!==i&&(t.splice(i,1),e.push(s),this._onObjectRemoved(s))})),e}forEachObject(t){this.getObjects().forEach(((e,s,i)=>t(e,s,i)))}getObjects(){for(var t=arguments.length,e=new Array(t),s=0;s<t;s++)e[s]=arguments[s];return 0===e.length?[...this._objects]:this._objects.filter((t=>t.isType(...e)))}item(t){return this._objects[t]}isEmpty(){return 0===this._objects.length}size(){return this._objects.length}contains(t,e){return!!this._objects.includes(t)||!!e&&this._objects.some((e=>e instanceof s&&e.contains(t,!0)))}complexity(){return this._objects.reduce(((t,e)=>t+=e.complexity?e.complexity():0),0)}sendObjectToBack(t){return!(!t||t===this._objects[0])&&(it(this._objects,t),this._objects.unshift(t),this._onStackOrderChanged(t),!0)}bringObjectToFront(t){return!(!t||t===this._objects[this._objects.length-1])&&(it(this._objects,t),this._objects.push(t),this._onStackOrderChanged(t),!0)}sendObjectBackwards(t,e){if(!t)return!1;const s=this._objects.indexOf(t);if(0!==s){const i=this.findNewLowerIndex(t,s,e);return it(this._objects,t),this._objects.splice(i,0,t),this._onStackOrderChanged(t),!0}return!1}bringObjectForward(t,e){if(!t)return!1;const s=this._objects.indexOf(t);if(s!==this._objects.length-1){const i=this.findNewUpperIndex(t,s,e);return it(this._objects,t),this._objects.splice(i,0,t),this._onStackOrderChanged(t),!0}return!1}moveObjectTo(t,e){return t!==this._objects[e]&&(it(this._objects,t),this._objects.splice(e,0,t),this._onStackOrderChanged(t),!0)}findNewLowerIndex(t,e,s){let i;if(s){i=e;for(let s=e-1;s>=0;--s)if(t.isOverlapping(this._objects[s])){i=s;break}}else i=e-1;return i}findNewUpperIndex(t,e,s){let i;if(s){i=e;for(let s=e+1;s<this._objects.length;++s)if(t.isOverlapping(this._objects[s])){i=s;break}}else i=e+1;return i}collectObjects(t){let{left:e,top:s,width:i,height:r}=t,{includeIntersecting:n=!0}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const o=[],a=new ot(e,s),h=a.add(new ot(i,r));for(let t=this._objects.length-1;t>=0;t--){const e=this._objects[t];e.selectable&&e.visible&&(n&&e.intersectsWithRect(a,h)||e.isContainedWithinRect(a,h)||n&&e.containsPoint(a)||n&&e.containsPoint(h))&&o.push(e)}return o}}return s}class lt extends st{_setOptions(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};for(const e in t)this.set(e,t[e])}_setObject(t){for(const e in t)this._set(e,t[e])}set(t,e){return"object"==typeof t?this._setObject(t):this._set(t,e),this}_set(t,e){this[t]=e}toggle(t){const e=this.get(t);return"boolean"==typeof e&&this.set(t,!e),this}get(t){return this[t]}}function ut(t){return v().requestAnimationFrame(t)}function dt(t){return v().cancelAnimationFrame(t)}let gt=0;const ft=()=>gt++,pt=()=>{const t=m().createElement("canvas");if(!t||void 0===t.getContext)throw new h("Failed to create `canvas` element");return t},mt=()=>m().createElement("img"),vt=t=>{const e=pt();return e.width=t.width,e.height=t.height,e},yt=(t,e,s)=>t.toDataURL("image/".concat(e),s),_t=(t,e,s)=>new Promise(((i,r)=>{t.toBlob(i,"image/".concat(e),s)})),xt=t=>t*w,Ct=t=>t/w,bt=t=>t.every(((t,e)=>t===T[e])),St=(t,e,s)=>new ot(t).transform(e,s),wt=t=>{const e=1/(t[0]*t[3]-t[1]*t[2]),s=[e*t[3],-e*t[1],-e*t[2],e*t[0],0,0],{x:i,y:r}=new ot(t[4],t[5]).transform(s,!0);return s[4]=-i,s[5]=-r,s},Tt=(t,e,s)=>[t[0]*e[0]+t[2]*e[1],t[1]*e[0]+t[3]*e[1],t[0]*e[2]+t[2]*e[3],t[1]*e[2]+t[3]*e[3],s?0:t[0]*e[4]+t[2]*e[5]+t[4],s?0:t[1]*e[4]+t[3]*e[5]+t[5]],Ot=(t,e)=>t.reduceRight(((t,s)=>s&&t?Tt(s,t,e):s||t),void 0)||T.concat(),kt=t=>{let[e,s]=t;return Math.atan2(s,e)},Dt=t=>{const e=kt(t),s=Math.pow(t[0],2)+Math.pow(t[1],2),i=Math.sqrt(s),r=(t[0]*t[3]-t[2]*t[1])/i,n=Math.atan2(t[0]*t[2]+t[1]*t[3],s);return{angle:Ct(e),scaleX:i,scaleY:r,skewX:Ct(n),skewY:0,translateX:t[4]||0,translateY:t[5]||0}},Mt=function(t){return[1,0,0,1,t,arguments.length>1&&void 0!==arguments[1]?arguments[1]:0]};function Pt(){let{angle:t=0}=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{},{x:e=0,y:s=0}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const i=xt(t),r=rt(i),n=nt(i);return[r,n,-n,r,e?e-(r*e-n*s):0,s?s-(n*e+r*s):0]}const Et=function(t){return[t,0,0,arguments.length>1&&void 0!==arguments[1]?arguments[1]:t,0,0]},At=t=>Math.tan(xt(t)),jt=t=>[1,0,At(t),1,0,0],Ft=t=>[1,At(t),0,1,0,0],Lt=t=>{let{scaleX:e=1,scaleY:s=1,flipX:i=!1,flipY:r=!1,skewX:n=0,skewY:o=0}=t,a=Et(i?-e:e,r?-s:s);return n&&(a=Tt(a,jt(n),!0)),o&&(a=Tt(a,Ft(o),!0)),a},Rt=t=>{const{translateX:e=0,translateY:s=0,angle:i=0}=t;let r=Mt(e,s);i&&(r=Tt(r,Pt({angle:i})));const n=Lt(t);return bt(n)||(r=Tt(r,n)),r},It=function(t){let{signal:e,crossOrigin:s=null}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};return new Promise((function(i,r){if(e&&e.aborted)return r(new c("loadImage"));const n=mt();let o;e&&(o=function(t){n.src="",r(t)},e.addEventListener("abort",o,{once:!0}));const a=function(){n.onload=n.onerror=null,o&&(null==e||e.removeEventListener("abort",o)),i(n)};t?(n.onload=a,n.onerror=function(){o&&(null==e||e.removeEventListener("abort",o)),r(new h("Error loading ".concat(n.src)))},s&&(n.crossOrigin=s),n.src=t):a()}))},Bt=function(t){let{signal:e,reviver:s=C}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};return new Promise(((i,r)=>{const n=[];e&&e.addEventListener("abort",r,{once:!0}),Promise.all(t.map((t=>tt.getClass(t.type).fromObject(t,{signal:e}).then((e=>(s(t,e),n.push(e),e)))))).then(i).catch((t=>{n.forEach((t=>{t.dispose&&t.dispose()})),r(t)})).finally((()=>{e&&e.removeEventListener("abort",r)}))}))},Xt=function(t){let{signal:e}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};return new Promise(((s,i)=>{const r=[];e&&e.addEventListener("abort",i,{once:!0});const n=Object.values(t).map((t=>t&&t.type&&tt.has(t.type)?Bt([t],{signal:e}).then((t=>{let[e]=t;return r.push(e),e})):t)),o=Object.keys(t);Promise.all(n).then((t=>t.reduce(((t,e,s)=>(t[o[s]]=e,t)),{}))).then(s).catch((t=>{r.forEach((t=>{t.dispose&&t.dispose()})),i(t)})).finally((()=>{e&&e.removeEventListener("abort",i)}))}))},Yt=function(t){return(arguments.length>1&&void 0!==arguments[1]?arguments[1]:[]).reduce(((e,s)=>(s in t&&(e[s]=t[s]),e)),{})},Wt=(t,e)=>Object.keys(t).reduce(((s,i)=>(e(t[i],i,t)&&(s[i]=t[i]),s)),{}),Vt=(t,e)=>parseFloat(Number(t).toFixed(e)),zt=t=>"matrix("+t.map((t=>Vt(t,o.NUM_FRACTION_DIGITS))).join(" ")+")",Gt=t=>!!t&&void 0!==t.toLive,Ht=t=>!!t&&"function"==typeof t.toObject,Nt=t=>!!t&&void 0!==t.offsetX&&"source"in t,Ut=t=>!!t&&"multiSelectionStacking"in t;function qt(t){const e=t&&Kt(t);let s=0,i=0;if(!t||!e)return{left:s,top:i};let r=t;const n=e.documentElement,o=e.body||{scrollLeft:0,scrollTop:0};for(;r&&(r.parentNode||r.host)&&(r=r.parentNode||r.host,r===e?(s=o.scrollLeft||n.scrollLeft||0,i=o.scrollTop||n.scrollTop||0):(s+=r.scrollLeft||0,i+=r.scrollTop||0),1!==r.nodeType||"fixed"!==r.style.position););return{left:s,top:i}}const Kt=t=>t.ownerDocument||null,Jt=t=>{var e;return(null===(e=t.ownerDocument)||void 0===e?void 0:e.defaultView)||null},Qt=function(t,e,s){let{width:i,height:r}=s,n=arguments.length>3&&void 0!==arguments[3]?arguments[3]:1;t.width=i,t.height=r,n>1&&(t.setAttribute("width",(i*n).toString()),t.setAttribute("height",(r*n).toString()),e.scale(n,n))},Zt=(t,e)=>{let{width:s,height:i}=e;s&&(t.style.width="number"==typeof s?"".concat(s,"px"):s),i&&(t.style.height="number"==typeof i?"".concat(i,"px"):i)};function $t(t){return void 0!==t.onselectstart&&(t.onselectstart=()=>!1),t.style.userSelect=j,t}class te{constructor(e){t(this,"_originalCanvasStyle",void 0),t(this,"lower",void 0);const s=this.createLowerCanvas(e);this.lower={el:s,ctx:s.getContext("2d")}}createLowerCanvas(t){const e=(s=t)&&void 0!==s.getContext?t:t&&m().getElementById(t)||pt();var s;if(e.hasAttribute("data-fabric"))throw new h("Trying to initialize a canvas that has already been initialized. Did you forget to dispose the canvas?");return this._originalCanvasStyle=e.style.cssText,e.setAttribute("data-fabric","main"),e.classList.add("lower-canvas"),e}cleanupDOM(t){let{width:e,height:s}=t;const{el:i}=this.lower;i.classList.remove("lower-canvas"),i.removeAttribute("data-fabric"),i.setAttribute("width","".concat(e)),i.setAttribute("height","".concat(s)),i.style.cssText=this._originalCanvasStyle||"",this._originalCanvasStyle=void 0}setDimensions(t,e){const{el:s,ctx:i}=this.lower;Qt(s,i,t,e)}setCSSDimensions(t){Zt(this.lower.el,t)}calcOffset(){return function(t){var e;const s=t&&Kt(t),i={left:0,top:0};if(!s)return i;const r=(null===(e=Jt(t))||void 0===e?void 0:e.getComputedStyle(t,null))||{};i.left+=parseInt(r.borderLeftWidth,10)||0,i.top+=parseInt(r.borderTopWidth,10)||0,i.left+=parseInt(r.paddingLeft,10)||0,i.top+=parseInt(r.paddingTop,10)||0;let n={left:0,top:0};const o=s.documentElement;void 0!==t.getBoundingClientRect&&(n=t.getBoundingClientRect());const a=qt(t);return{left:n.left+a.left-(o.clientLeft||0)+i.left,top:n.top+a.top-(o.clientTop||0)+i.top}}(this.lower.el)}dispose(){p().dispose(this.lower.el),delete this.lower}}const ee={backgroundVpt:!0,backgroundColor:"",overlayVpt:!0,overlayColor:"",includeDefaultValues:!0,svgViewportTransformation:!0,renderOnAddRemove:!0,skipOffscreen:!0,enableRetinaScaling:!0,imageSmoothingEnabled:!0,controlsAboveOverlay:!1,allowTouchScrolling:!1,viewportTransform:[...T]},se=["objects"];class ie extends(ct(lt)){get lowerCanvasEl(){var t;return null===(t=this.elements.lower)||void 0===t?void 0:t.el}get contextContainer(){var t;return null===(t=this.elements.lower)||void 0===t?void 0:t.ctx}static getDefaults(){return ie.ownDefaults}constructor(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};super(),Object.assign(this,this.constructor.getDefaults()),this.set(e),this.initElements(t),this._setDimensionsImpl({width:this.width||this.elements.lower.el.width||0,height:this.height||this.elements.lower.el.height||0}),this.skipControlsDrawing=!1,this.viewportTransform=[...this.viewportTransform],this.calcViewportBoundaries()}initElements(t){this.elements=new te(t)}add(){const t=super.add(...arguments);return arguments.length>0&&this.renderOnAddRemove&&this.requestRenderAll(),t}insertAt(t){for(var e=arguments.length,s=new Array(e>1?e-1:0),i=1;i<e;i++)s[i-1]=arguments[i];const r=super.insertAt(t,...s);return s.length>0&&this.renderOnAddRemove&&this.requestRenderAll(),r}remove(){const t=super.remove(...arguments);return t.length>0&&this.renderOnAddRemove&&this.requestRenderAll(),t}_onObjectAdded(t){t.canvas&&t.canvas!==this&&(a("warn","Canvas is trying to add an object that belongs to a different canvas.\nResulting to default behavior: removing object from previous canvas and adding to new canvas"),t.canvas.remove(t)),t._set("canvas",this),t.setCoords(),this.fire("object:added",{target:t}),t.fire("added",{target:this})}_onObjectRemoved(t){t._set("canvas",void 0),this.fire("object:removed",{target:t}),t.fire("removed",{target:this})}_onStackOrderChanged(){this.renderOnAddRemove&&this.requestRenderAll()}getRetinaScaling(){return this.enableRetinaScaling?y():1}calcOffset(){return this._offset=this.elements.calcOffset()}getWidth(){return this.width}getHeight(){return this.height}setWidth(t,e){return this.setDimensions({width:t},e)}setHeight(t,e){return this.setDimensions({height:t},e)}_setDimensionsImpl(t){let{cssOnly:e=!1,backstoreOnly:i=!1}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};if(!e){const e=s({width:this.width,height:this.height},t);this.elements.setDimensions(e,this.getRetinaScaling()),this.hasLostContext=!0,this.width=e.width,this.height=e.height}i||this.elements.setCSSDimensions(t),this.calcOffset()}setDimensions(t,e){this._setDimensionsImpl(t,e),e&&e.cssOnly||this.requestRenderAll()}getZoom(){return this.viewportTransform[0]}setViewportTransform(t){this.viewportTransform=t,this.calcViewportBoundaries(),this.renderOnAddRemove&&this.requestRenderAll()}zoomToPoint(t,e){const s=t,i=[...this.viewportTransform],r=St(t,wt(i));i[0]=e,i[3]=e;const n=St(r,i);i[4]+=s.x-n.x,i[5]+=s.y-n.y,this.setViewportTransform(i)}setZoom(t){this.zoomToPoint(new ot(0,0),t)}absolutePan(t){const e=[...this.viewportTransform];return e[4]=-t.x,e[5]=-t.y,this.setViewportTransform(e)}relativePan(t){return this.absolutePan(new ot(-t.x-this.viewportTransform[4],-t.y-this.viewportTransform[5]))}getElement(){return this.elements.lower.el}clearContext(t){t.clearRect(0,0,this.width,this.height)}getContext(){return this.elements.lower.ctx}clear(){this.remove(...this.getObjects()),this.backgroundImage=void 0,this.overlayImage=void 0,this.backgroundColor="",this.overlayColor="",this.clearContext(this.getContext()),this.fire("canvas:cleared"),this.renderOnAddRemove&&this.requestRenderAll()}renderAll(){this.cancelRequestedRender(),this.destroyed||this.renderCanvas(this.getContext(),this._objects)}renderAndReset(){this.nextRenderHandle=0,this.renderAll()}requestRenderAll(){this.nextRenderHandle||this.disposed||this.destroyed||(this.nextRenderHandle=ut((()=>this.renderAndReset())))}calcViewportBoundaries(){const t=this.width,e=this.height,s=wt(this.viewportTransform),i=St({x:0,y:0},s),r=St({x:t,y:e},s),n=i.min(r),o=i.max(r);return this.vptCoords={tl:n,tr:new ot(o.x,n.y),bl:new ot(n.x,o.y),br:o}}cancelRequestedRender(){this.nextRenderHandle&&(dt(this.nextRenderHandle),this.nextRenderHandle=0)}drawControls(t){}renderCanvas(t,e){if(this.destroyed)return;const s=this.viewportTransform,i=this.clipPath;this.calcViewportBoundaries(),this.clearContext(t),t.imageSmoothingEnabled=this.imageSmoothingEnabled,t.patternQuality="best",this.fire("before:render",{ctx:t}),this._renderBackground(t),t.save(),t.transform(s[0],s[1],s[2],s[3],s[4],s[5]),this._renderObjects(t,e),t.restore(),this.controlsAboveOverlay||this.skipControlsDrawing||this.drawControls(t),i&&(i._set("canvas",this),i.shouldCache(),i._transformDone=!0,i.renderCache({forClipping:!0}),this.drawClipPathOnCanvas(t,i)),this._renderOverlay(t),this.controlsAboveOverlay&&!this.skipControlsDrawing&&this.drawControls(t),this.fire("after:render",{ctx:t}),this.__cleanupTask&&(this.__cleanupTask(),this.__cleanupTask=void 0)}drawClipPathOnCanvas(t,e){const s=this.viewportTransform;t.save(),t.transform(...s),t.globalCompositeOperation="destination-in",e.transform(t),t.scale(1/e.zoomX,1/e.zoomY),t.drawImage(e._cacheCanvas,-e.cacheTranslationX,-e.cacheTranslationY),t.restore()}_renderObjects(t,e){for(let s=0,i=e.length;s<i;++s)e[s]&&e[s].render(t)}_renderBackgroundOrOverlay(t,e){const s=this["".concat(e,"Color")],i=this["".concat(e,"Image")],r=this.viewportTransform,n=this["".concat(e,"Vpt")];if(!s&&!i)return;const o=Gt(s);if(s){if(t.save(),t.beginPath(),t.moveTo(0,0),t.lineTo(this.width,0),t.lineTo(this.width,this.height),t.lineTo(0,this.height),t.closePath(),t.fillStyle=o?s.toLive(t):s,n&&t.transform(...r),o){t.transform(1,0,0,1,s.offsetX||0,s.offsetY||0);const e=s.gradientTransform||s.patternTransform;e&&t.transform(...e)}t.fill(),t.restore()}if(i){t.save();const{skipOffscreen:e}=this;this.skipOffscreen=n,n&&t.transform(...r),i.render(t),this.skipOffscreen=e,t.restore()}}_renderBackground(t){this._renderBackgroundOrOverlay(t,"background")}_renderOverlay(t){this._renderBackgroundOrOverlay(t,"overlay")}getCenter(){return{top:this.height/2,left:this.width/2}}getCenterPoint(){return new ot(this.width/2,this.height/2)}centerObjectH(t){return this._centerObject(t,new ot(this.getCenterPoint().x,t.getCenterPoint().y))}centerObjectV(t){return this._centerObject(t,new ot(t.getCenterPoint().x,this.getCenterPoint().y))}centerObject(t){return this._centerObject(t,this.getCenterPoint())}viewportCenterObject(t){return this._centerObject(t,this.getVpCenter())}viewportCenterObjectH(t){return this._centerObject(t,new ot(this.getVpCenter().x,t.getCenterPoint().y))}viewportCenterObjectV(t){return this._centerObject(t,new ot(t.getCenterPoint().x,this.getVpCenter().y))}getVpCenter(){return St(this.getCenterPoint(),wt(this.viewportTransform))}_centerObject(t,e){t.setXY(e,D,D),t.setCoords(),this.renderOnAddRemove&&this.requestRenderAll()}toDatalessJSON(t){return this.toDatalessObject(t)}toObject(t){return this._toObjectMethod("toObject",t)}toJSON(){return this.toObject()}toDatalessObject(t){return this._toObjectMethod("toDatalessObject",t)}_toObjectMethod(t,e){const i=this.clipPath,r=i&&!i.excludeFromExport?this._toObject(i,t,e):null;return s(s(s({version:x},Yt(this,e)),{},{objects:this._objects.filter((t=>!t.excludeFromExport)).map((s=>this._toObject(s,t,e)))},this.__serializeBgOverlay(t,e)),r?{clipPath:r}:null)}_toObject(t,e,s){let i;this.includeDefaultValues||(i=t.includeDefaultValues,t.includeDefaultValues=!1);const r=t[e](s);return this.includeDefaultValues||(t.includeDefaultValues=!!i),r}__serializeBgOverlay(t,e){const s={},i=this.backgroundImage,r=this.overlayImage,n=this.backgroundColor,o=this.overlayColor;return Gt(n)?n.excludeFromExport||(s.background=n.toObject(e)):n&&(s.background=n),Gt(o)?o.excludeFromExport||(s.overlay=o.toObject(e)):o&&(s.overlay=o),i&&!i.excludeFromExport&&(s.backgroundImage=this._toObject(i,t,e)),r&&!r.excludeFromExport&&(s.overlayImage=this._toObject(r,t,e)),s}toSVG(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{},e=arguments.length>1?arguments[1]:void 0;t.reviver=e;const s=[];return this._setSVGPreamble(s,t),this._setSVGHeader(s,t),this.clipPath&&s.push('<g clip-path="url(#'.concat(this.clipPath.clipPathId,')" >\n')),this._setSVGBgOverlayColor(s,"background"),this._setSVGBgOverlayImage(s,"backgroundImage",e),this._setSVGObjects(s,e),this.clipPath&&s.push("</g>\n"),this._setSVGBgOverlayColor(s,"overlay"),this._setSVGBgOverlayImage(s,"overlayImage",e),s.push("</svg>"),s.join("")}_setSVGPreamble(t,e){e.suppressPreamble||t.push('<?xml version="1.0" encoding="',e.encoding||"UTF-8",'" standalone="no" ?>\n','<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" ','"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">\n')}_setSVGHeader(t,e){const s=e.width||"".concat(this.width),i=e.height||"".concat(this.height),r=o.NUM_FRACTION_DIGITS,n=e.viewBox;let a;if(n)a='viewBox="'.concat(n.x," ").concat(n.y," ").concat(n.width," ").concat(n.height,'" ');else if(this.svgViewportTransformation){const t=this.viewportTransform;a='viewBox="'.concat(Vt(-t[4]/t[0],r)," ").concat(Vt(-t[5]/t[3],r)," ").concat(Vt(this.width/t[0],r)," ").concat(Vt(this.height/t[3],r),'" ')}else a='viewBox="0 0 '.concat(this.width," ").concat(this.height,'" ');t.push("<svg ",'xmlns="http://www.w3.org/2000/svg" ','xmlns:xlink="http://www.w3.org/1999/xlink" ','version="1.1" ','width="',s,'" ','height="',i,'" ',a,'xml:space="preserve">\n',"<desc>Created with Fabric.js ",x,"</desc>\n","<defs>\n",this.createSVGFontFacesMarkup(),this.createSVGRefElementsMarkup(),this.createSVGClipPathMarkup(e),"</defs>\n")}createSVGClipPathMarkup(t){const e=this.clipPath;return e?(e.clipPathId="CLIPPATH_".concat(ft()),'<clipPath id="'.concat(e.clipPathId,'" >\n').concat(e.toClipPathSVG(t.reviver),"</clipPath>\n")):""}createSVGRefElementsMarkup(){return["background","overlay"].map((t=>{const e=this["".concat(t,"Color")];if(Gt(e)){const s=this["".concat(t,"Vpt")],i=this.viewportTransform,r={isType:()=>!1,width:this.width/(s?i[0]:1),height:this.height/(s?i[3]:1)};return e.toSVG(r,{additionalTransform:s?zt(i):""})}})).join("")}createSVGFontFacesMarkup(){const t=[],e={},s=o.fontPaths;this._objects.forEach((function e(s){t.push(s),ht(s)&&s._objects.forEach(e)})),t.forEach((t=>{if(!(i=t)||"function"!=typeof i._renderText)return;var i;const{styles:r,fontFamily:n}=t;!e[n]&&s[n]&&(e[n]=!0,r&&Object.values(r).forEach((t=>{Object.values(t).forEach((t=>{let{fontFamily:i=""}=t;!e[i]&&s[i]&&(e[i]=!0)}))})))}));const i=Object.keys(e).map((t=>"\t\t@font-face {\n\t\t\tfont-family: '".concat(t,"';\n\t\t\tsrc: url('").concat(s[t],"');\n\t\t}\n"))).join("");return i?'\t<style type="text/css"><![CDATA[\n'.concat(i,"]]></style>\n"):""}_setSVGObjects(t,e){this.forEachObject((s=>{s.excludeFromExport||this._setSVGObject(t,s,e)}))}_setSVGObject(t,e,s){t.push(e.toSVG(s))}_setSVGBgOverlayImage(t,e,s){const i=this[e];i&&!i.excludeFromExport&&i.toSVG&&t.push(i.toSVG(s))}_setSVGBgOverlayColor(t,e){const s=this["".concat(e,"Color")];if(s)if(Gt(s)){const i=s.repeat||"",r=this.width,n=this.height,o=this["".concat(e,"Vpt")]?zt(wt(this.viewportTransform)):"";t.push('<rect transform="'.concat(o," translate(").concat(r/2,",").concat(n/2,')" x="').concat(s.offsetX-r/2,'" y="').concat(s.offsetY-n/2,'" width="').concat("repeat-y"!==i&&"no-repeat"!==i||!Nt(s)?r:s.source.width,'" height="').concat("repeat-x"!==i&&"no-repeat"!==i||!Nt(s)?n:s.source.height,'" fill="url(#SVGID_').concat(s.id,')"></rect>\n'))}else t.push('<rect x="0" y="0" width="100%" height="100%" ','fill="',s,'"',"></rect>\n")}loadFromJSON(t,e){let{signal:s}=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{};if(!t)return Promise.reject(new h("`json` is undefined"));const r="string"==typeof t?JSON.parse(t):t,{objects:n=[]}=r,o=i(r,se),{backgroundImage:a,background:c,overlayImage:l,overlay:u,clipPath:d}=o,g=this.renderOnAddRemove;return this.renderOnAddRemove=!1,Promise.all([Bt(n,{reviver:e,signal:s}),Xt({backgroundImage:a,backgroundColor:c,overlayImage:l,overlayColor:u,clipPath:d},{signal:s})]).then((t=>{let[e,s]=t;return this.clear(),this.add(...e),this.set(o),this.set(s),this.renderOnAddRemove=g,this}))}clone(t){const e=this.toObject(t);return this.cloneWithoutData().loadFromJSON(e)}cloneWithoutData(){const t=vt(this);return new this.constructor(t)}toDataURL(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};const{format:e="png",quality:s=1,multiplier:i=1,enableRetinaScaling:r=!1}=t,n=i*(r?this.getRetinaScaling():1);return yt(this.toCanvasElement(n,t),e,s)}toBlob(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};const{format:e="png",quality:s=1,multiplier:i=1,enableRetinaScaling:r=!1}=t,n=i*(r?this.getRetinaScaling():1);return _t(this.toCanvasElement(n,t),e,s)}toCanvasElement(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:1,{width:e,height:s,left:i,top:r,filter:n}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const o=(e||this.width)*t,a=(s||this.height)*t,h=this.getZoom(),c=this.width,l=this.height,u=this.skipControlsDrawing,d=h*t,g=this.viewportTransform,f=[d,0,0,d,(g[4]-(i||0))*t,(g[5]-(r||0))*t],p=this.enableRetinaScaling,m=vt({width:o,height:a}),v=n?this._objects.filter((t=>n(t))):this._objects;return this.enableRetinaScaling=!1,this.viewportTransform=f,this.width=o,this.height=a,this.skipControlsDrawing=!0,this.calcViewportBoundaries(),this.renderCanvas(m.getContext("2d"),v),this.viewportTransform=g,this.width=c,this.height=l,this.calcViewportBoundaries(),this.enableRetinaScaling=p,this.skipControlsDrawing=u,m}dispose(){return!this.disposed&&this.elements.cleanupDOM({width:this.width,height:this.height}),et.cancelByCanvas(this),this.disposed=!0,new Promise(((t,e)=>{const s=()=>{this.destroy(),t(!0)};s.kill=e,this.__cleanupTask&&this.__cleanupTask.kill("aborted"),this.destroyed?t(!1):this.nextRenderHandle?this.__cleanupTask=s:s()}))}destroy(){this.destroyed=!0,this.cancelRequestedRender(),this.forEachObject((t=>t.dispose())),this._objects=[],this.backgroundImage&&this.backgroundImage.dispose(),this.backgroundImage=void 0,this.overlayImage&&this.overlayImage.dispose(),this.overlayImage=void 0,this.elements.dispose()}toString(){return"#<Canvas (".concat(this.complexity(),"): { objects: ").concat(this._objects.length," }>")}}t(ie,"ownDefaults",ee);const re=["touchstart","touchmove","touchend"];const ne=t=>{const e=qt(t.target),s=function(t){const e=t.changedTouches;return e&&e[0]?e[0]:t}(t);return new ot(s.clientX+e.left,s.clientY+e.top)},oe=t=>re.includes(t.type)||"touch"===t.pointerType,ae=t=>{t.preventDefault(),t.stopPropagation()},he=t=>{let e=0,s=0,i=0,r=0;for(let n=0,o=t.length;n<o;n++){const{x:o,y:a}=t[n];(o>i||!n)&&(i=o),(o<e||!n)&&(e=o),(a>r||!n)&&(r=a),(a<s||!n)&&(s=a)}return{left:e,top:s,width:i-e,height:r-s}},ce=["translateX","translateY","scaleX","scaleY"],le=(t,e)=>ue(t,Tt(e,t.calcOwnMatrix())),ue=(t,e)=>{const s=Dt(e),{translateX:r,translateY:n,scaleX:o,scaleY:a}=s,h=i(s,ce),c=new ot(r,n);t.flipX=!1,t.flipY=!1,Object.assign(t,h),t.set({scaleX:o,scaleY:a}),t.setPositionByOrigin(c,D,D)},de=t=>{t.scaleX=1,t.scaleY=1,t.skewX=0,t.skewY=0,t.flipX=!1,t.flipY=!1,t.rotate(0)},ge=t=>({scaleX:t.scaleX,scaleY:t.scaleY,skewX:t.skewX,skewY:t.skewY,angle:t.angle,left:t.left,flipX:t.flipX,flipY:t.flipY,top:t.top}),fe=(t,e,s)=>{const i=t/2,r=e/2,n=[new ot(-i,-r),new ot(i,-r),new ot(-i,r),new ot(i,r)].map((t=>t.transform(s))),o=he(n);return new ot(o.width,o.height)},pe=function(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:T;return Tt(wt(arguments.length>1&&void 0!==arguments[1]?arguments[1]:T),t)},me=function(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:T,s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:T;return t.transform(pe(e,s))},ve=function(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:T,s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:T;return t.transform(pe(e,s),!0)},ye=(t,e,s)=>{const i=pe(e,s);return ue(t,Tt(i,t.calcOwnMatrix())),i},_e=(t,e)=>{var i;const{transform:{target:r}}=e;null===(i=r.canvas)||void 0===i||i.fire("object:".concat(t),s(s({},e),{},{target:r})),r.fire(t,e)},xe={left:-.5,top:-.5,center:0,bottom:.5,right:.5},Ce=t=>"string"==typeof t?xe[t]:t-.5,be="not-allowed";function Se(t){return Ce(t.originX)===Ce(D)&&Ce(t.originY)===Ce(D)}function we(t){return.5-Ce(t)}const Te=(t,e)=>t[e],Oe=(t,e,s,i)=>({e:t,transform:e,pointer:new ot(s,i)});function ke(t,e){const s=t.getTotalAngle()+Ct(Math.atan2(e.y,e.x))+360;return Math.round(s%360/45)}function De(t,e,s,i,r){var n;let{target:o,corner:a}=t;const h=o.controls[a],c=(null===(n=o.canvas)||void 0===n?void 0:n.getZoom())||1,l=o.padding/c,u=function(t,e,s,i){const r=t.getRelativeCenterPoint(),n=void 0!==s&&void 0!==i?t.translateToGivenOrigin(r,D,D,s,i):new ot(t.left,t.top);return(t.angle?e.rotate(-xt(t.angle),r):e).subtract(n)}(o,new ot(i,r),e,s);return u.x>=l&&(u.x-=l),u.x<=-l&&(u.x+=l),u.y>=l&&(u.y-=l),u.y<=l&&(u.y+=l),u.x-=h.offsetX,u.y-=h.offsetY,u}const Me=(t,e,s,i)=>{const{target:r,offsetX:n,offsetY:o}=e,a=s-n,h=i-o,c=!Te(r,"lockMovementX")&&r.left!==a,l=!Te(r,"lockMovementY")&&r.top!==h;return c&&r.set(M,a),l&&r.set(P,h),(c||l)&&_e(L,Oe(t,e,s,i)),c||l},Pe=t=>t.replace(/\s+/g," "),Ee={aliceblue:"#F0F8FF",antiquewhite:"#FAEBD7",aqua:"#0FF",aquamarine:"#7FFFD4",azure:"#F0FFFF",beige:"#F5F5DC",bisque:"#FFE4C4",black:"#000",blanchedalmond:"#FFEBCD",blue:"#00F",blueviolet:"#8A2BE2",brown:"#A52A2A",burlywood:"#DEB887",cadetblue:"#5F9EA0",chartreuse:"#7FFF00",chocolate:"#D2691E",coral:"#FF7F50",cornflowerblue:"#6495ED",cornsilk:"#FFF8DC",crimson:"#DC143C",cyan:"#0FF",darkblue:"#00008B",darkcyan:"#008B8B",darkgoldenrod:"#B8860B",darkgray:"#A9A9A9",darkgrey:"#A9A9A9",darkgreen:"#006400",darkkhaki:"#BDB76B",darkmagenta:"#8B008B",darkolivegreen:"#556B2F",darkorange:"#FF8C00",darkorchid:"#9932CC",darkred:"#8B0000",darksalmon:"#E9967A",darkseagreen:"#8FBC8F",darkslateblue:"#483D8B",darkslategray:"#2F4F4F",darkslategrey:"#2F4F4F",darkturquoise:"#00CED1",darkviolet:"#9400D3",deeppink:"#FF1493",deepskyblue:"#00BFFF",dimgray:"#696969",dimgrey:"#696969",dodgerblue:"#1E90FF",firebrick:"#B22222",floralwhite:"#FFFAF0",forestgreen:"#228B22",fuchsia:"#F0F",gainsboro:"#DCDCDC",ghostwhite:"#F8F8FF",gold:"#FFD700",goldenrod:"#DAA520",gray:"#808080",grey:"#808080",green:"#008000",greenyellow:"#ADFF2F",honeydew:"#F0FFF0",hotpink:"#FF69B4",indianred:"#CD5C5C",indigo:"#4B0082",ivory:"#FFFFF0",khaki:"#F0E68C",lavender:"#E6E6FA",lavenderblush:"#FFF0F5",lawngreen:"#7CFC00",lemonchiffon:"#FFFACD",lightblue:"#ADD8E6",lightcoral:"#F08080",lightcyan:"#E0FFFF",lightgoldenrodyellow:"#FAFAD2",lightgray:"#D3D3D3",lightgrey:"#D3D3D3",lightgreen:"#90EE90",lightpink:"#FFB6C1",lightsalmon:"#FFA07A",lightseagreen:"#20B2AA",lightskyblue:"#87CEFA",lightslategray:"#789",lightslategrey:"#789",lightsteelblue:"#B0C4DE",lightyellow:"#FFFFE0",lime:"#0F0",limegreen:"#32CD32",linen:"#FAF0E6",magenta:"#F0F",maroon:"#800000",mediumaquamarine:"#66CDAA",mediumblue:"#0000CD",mediumorchid:"#BA55D3",mediumpurple:"#9370DB",mediumseagreen:"#3CB371",mediumslateblue:"#7B68EE",mediumspringgreen:"#00FA9A",mediumturquoise:"#48D1CC",mediumvioletred:"#C71585",midnightblue:"#191970",mintcream:"#F5FFFA",mistyrose:"#FFE4E1",moccasin:"#FFE4B5",navajowhite:"#FFDEAD",navy:"#000080",oldlace:"#FDF5E6",olive:"#808000",olivedrab:"#6B8E23",orange:"#FFA500",orangered:"#FF4500",orchid:"#DA70D6",palegoldenrod:"#EEE8AA",palegreen:"#98FB98",paleturquoise:"#AFEEEE",palevioletred:"#DB7093",papayawhip:"#FFEFD5",peachpuff:"#FFDAB9",peru:"#CD853F",pink:"#FFC0CB",plum:"#DDA0DD",powderblue:"#B0E0E6",purple:"#800080",rebeccapurple:"#639",red:"#F00",rosybrown:"#BC8F8F",royalblue:"#4169E1",saddlebrown:"#8B4513",salmon:"#FA8072",sandybrown:"#F4A460",seagreen:"#2E8B57",seashell:"#FFF5EE",sienna:"#A0522D",silver:"#C0C0C0",skyblue:"#87CEEB",slateblue:"#6A5ACD",slategray:"#708090",slategrey:"#708090",snow:"#FFFAFA",springgreen:"#00FF7F",steelblue:"#4682B4",tan:"#D2B48C",teal:"#008080",thistle:"#D8BFD8",tomato:"#FF6347",turquoise:"#40E0D0",violet:"#EE82EE",wheat:"#F5DEB3",white:"#FFF",whitesmoke:"#F5F5F5",yellow:"#FF0",yellowgreen:"#9ACD32"},Ae=(t,e,s)=>(s<0&&(s+=1),s>1&&(s-=1),s<1/6?t+6*(e-t)*s:s<.5?e:s<2/3?t+(e-t)*(2/3-s)*6:t),je=(t,e,s,i)=>{t/=255,e/=255,s/=255;const r=Math.max(t,e,s),n=Math.min(t,e,s);let o,a;const h=(r+n)/2;if(r===n)o=a=0;else{const i=r-n;switch(a=h>.5?i/(2-r-n):i/(r+n),r){case t:o=(e-s)/i+(e<s?6:0);break;case e:o=(s-t)/i+2;break;case s:o=(t-e)/i+4}o/=6}return[Math.round(360*o),Math.round(100*a),Math.round(100*h),i]},Fe=function(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"1";return parseFloat(t)/(t.endsWith("%")?100:1)},Le=t=>Math.min(Math.round(t),255).toString(16).toUpperCase().padStart(2,"0"),Re=t=>{let[e,s,i,r=1]=t;const n=Math.round(.3*e+.59*s+.11*i);return[n,n,n,r]};class Ie{constructor(e){if(t(this,"isUnrecognised",!1),e)if(e instanceof Ie)this.setSource([...e._source]);else if(Array.isArray(e)){const[t,s,i,r=1]=e;this.setSource([t,s,i,r])}else this.setSource(this._tryParsingColor(e));else this.setSource([0,0,0,1])}_tryParsingColor(t){return(t=t.toLowerCase())in Ee&&(t=Ee[t]),"transparent"===t?[255,255,255,0]:Ie.sourceFromHex(t)||Ie.sourceFromRgb(t)||Ie.sourceFromHsl(t)||(this.isUnrecognised=!0)&&[0,0,0,1]}getSource(){return this._source}setSource(t){this._source=t}toRgb(){const[t,e,s]=this.getSource();return"rgb(".concat(t,",").concat(e,",").concat(s,")")}toRgba(){return"rgba(".concat(this.getSource().join(","),")")}toHsl(){const[t,e,s]=je(...this.getSource());return"hsl(".concat(t,",").concat(e,"%,").concat(s,"%)")}toHsla(){const[t,e,s,i]=je(...this.getSource());return"hsla(".concat(t,",").concat(e,"%,").concat(s,"%,").concat(i,")")}toHex(){return this.toHexa().slice(0,6)}toHexa(){const[t,e,s,i]=this.getSource();return"".concat(Le(t)).concat(Le(e)).concat(Le(s)).concat(Le(Math.round(255*i)))}getAlpha(){return this.getSource()[3]}setAlpha(t){return this._source[3]=t,this}toGrayscale(){return this.setSource(Re(this.getSource())),this}toBlackWhite(t){const[e,,,s]=Re(this.getSource()),i=e<(t||127)?0:255;return this.setSource([i,i,i,s]),this}overlayWith(t){t instanceof Ie||(t=new Ie(t));const e=this.getSource(),s=t.getSource(),[i,r,n]=e.map(((t,e)=>Math.round(.5*t+.5*s[e])));return this.setSource([i,r,n,e[3]]),this}static fromRgb(t){return Ie.fromRgba(t)}static fromRgba(t){return new Ie(Ie.sourceFromRgb(t))}static sourceFromRgb(t){const e=Pe(t).match(/^rgba?\(\s?(\d{0,3}(?:\.\d+)?%?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?(?:\s?[,/]\s?(\d{0,3}(?:\.\d+)?%?)\s?)?\)$/i);if(e){const[t,s,i]=e.slice(1,4).map((t=>{const e=parseFloat(t);return t.endsWith("%")?Math.round(2.55*e):e}));return[t,s,i,Fe(e[4])]}}static fromHsl(t){return Ie.fromHsla(t)}static fromHsla(t){return new Ie(Ie.sourceFromHsl(t))}static sourceFromHsl(t){const e=Pe(t).match(/^hsla?\(\s?([+-]?\d{0,3}(?:\.\d+)?(?:deg|turn|rad)?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?(?:\s?[,/]\s?(\d*(?:\.\d+)?%?)\s?)?\)$/i);if(!e)return;const s=(Ie.parseAngletoDegrees(e[1])%360+360)%360/360,i=parseFloat(e[2])/100,r=parseFloat(e[3])/100;let n,o,a;if(0===i)n=o=a=r;else{const t=r<=.5?r*(i+1):r+i-r*i,e=2*r-t;n=Ae(e,t,s+1/3),o=Ae(e,t,s),a=Ae(e,t,s-1/3)}return[Math.round(255*n),Math.round(255*o),Math.round(255*a),Fe(e[4])]}static fromHex(t){return new Ie(Ie.sourceFromHex(t))}static sourceFromHex(t){if(t.match(/^#?(([0-9a-f]){3,4}|([0-9a-f]{2}){3,4})$/i)){const e=t.slice(t.indexOf("#")+1);let s;s=e.length<=4?e.split("").map((t=>t+t)):e.match(/.{2}/g);const[i,r,n,o=255]=s.map((t=>parseInt(t,16)));return[i,r,n,o/255]}}static parseAngletoDegrees(t){const e=t.toLowerCase(),s=parseFloat(e);return e.includes("rad")?Ct(s):e.includes("turn")?360*s:s}}const Be=function(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:O;const s=/\D{0,2}$/.exec(t),i=parseFloat(t),r=o.DPI;switch(null==s?void 0:s[0]){case"mm":return i*r/25.4;case"cm":return i*r/2.54;case"in":return i*r;case"pt":return i*r/72;case"pc":return i*r/72*12;case"em":return i*e;default:return i}},Xe=t=>{const[e,s]=t.trim().split(" "),[i,r]=(n=e)&&n!==j?[n.slice(1,4),n.slice(5,8)]:n===j?[n,n]:["Mid","Mid"];var n;return{meetOrSlice:s||"meet",alignX:i,alignY:r}},Ye=function(t,e){let s,i,r=!(arguments.length>2&&void 0!==arguments[2])||arguments[2];if(e)if(e.toLive)s="url(#SVGID_".concat(e.id,")");else{const t=new Ie(e),r=t.getAlpha();s=t.toRgb(),1!==r&&(i=r.toString())}else s="none";return r?"".concat(t,": ").concat(s,"; ").concat(i?"".concat(t,"-opacity: ").concat(i,"; "):""):"".concat(t,'="').concat(s,'" ').concat(i?"".concat(t,'-opacity="').concat(i,'" '):"")};class We{getSvgStyles(t){const e=this.fillRule?this.fillRule:"nonzero",s=this.strokeWidth?this.strokeWidth:"0",i=this.strokeDashArray?this.strokeDashArray.join(" "):j,r=this.strokeDashOffset?this.strokeDashOffset:"0",n=this.strokeLineCap?this.strokeLineCap:"butt",o=this.strokeLineJoin?this.strokeLineJoin:"miter",a=this.strokeMiterLimit?this.strokeMiterLimit:"4",h=void 0!==this.opacity?this.opacity:"1",c=this.visible?"":" visibility: hidden;",l=t?"":this.getSvgFilter(),u=Ye(K,this.fill);return[Ye(J,this.stroke),"stroke-width: ",s,"; ","stroke-dasharray: ",i,"; ","stroke-linecap: ",n,"; ","stroke-dashoffset: ",r,"; ","stroke-linejoin: ",o,"; ","stroke-miterlimit: ",a,"; ",u,"fill-rule: ",e,"; ","opacity: ",h,";",l,c].join("")}getSvgFilter(){return this.shadow?"filter: url(#SVGID_".concat(this.shadow.id,");"):""}getSvgCommons(){return[this.id?'id="'.concat(this.id,'" '):"",this.clipPath?'clip-path="url(#'.concat(this.clipPath.clipPathId,')" '):""].join("")}getSvgTransform(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:"";const s=t?this.calcTransformMatrix():this.calcOwnMatrix(),i='transform="'.concat(zt(s));return"".concat(i).concat(e,'" ')}_toSVG(t){return[""]}toSVG(t){return this._createBaseSVGMarkup(this._toSVG(t),{reviver:t})}toClipPathSVG(t){return"\t"+this._createBaseClipPathSVGMarkup(this._toSVG(t),{reviver:t})}_createBaseClipPathSVGMarkup(t){let{reviver:e,additionalTransform:s=""}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const i=[this.getSvgTransform(!0,s),this.getSvgCommons()].join(""),r=t.indexOf("COMMON_PARTS");return t[r]=i,e?e(t.join("")):t.join("")}_createBaseSVGMarkup(t){let{noStyle:e,reviver:s,withShadow:i,additionalTransform:r}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const n=e?"":'style="'.concat(this.getSvgStyles(),'" '),o=i?'style="'.concat(this.getSvgFilter(),'" '):"",a=this.clipPath,h=this.strokeUniform?'vector-effect="non-scaling-stroke" ':"",c=a&&a.absolutePositioned,l=this.stroke,u=this.fill,d=this.shadow,g=[],f=t.indexOf("COMMON_PARTS");let p;a&&(a.clipPathId="CLIPPATH_".concat(ft()),p='<clipPath id="'.concat(a.clipPathId,'" >\n').concat(a.toClipPathSVG(s),"</clipPath>\n")),c&&g.push("<g ",o,this.getSvgCommons()," >\n"),g.push("<g ",this.getSvgTransform(!1),c?"":o+this.getSvgCommons()," >\n");const m=[n,h,e?"":this.addPaintOrder()," ",r?'transform="'.concat(r,'" '):""].join("");return t[f]=m,Gt(u)&&g.push(u.toSVG(this)),Gt(l)&&g.push(l.toSVG(this)),d&&g.push(d.toSVG(this)),a&&g.push(p),g.push(t.join("")),g.push("</g>\n"),c&&g.push("</g>\n"),s?s(g.join("")):g.join("")}addPaintOrder(){return this.paintFirst!==K?' paint-order="'.concat(this.paintFirst,'" '):""}}function Ve(t){return new RegExp("^("+t.join("|")+")\\b","i")}const ze="textDecorationThickness",Ge=["fontSize","fontWeight","fontFamily","fontStyle"],He=["underline","overline","linethrough"],Ne=[...Ge,"lineHeight","text","charSpacing","textAlign","styles","path","pathStartOffset","pathSide","pathAlign"],Ue=[...Ne,...He,"textBackgroundColor","direction",ze],qe=[...Ge,...He,J,"strokeWidth",K,"deltaY","textBackgroundColor",ze],Ke={_reNewline:F,_reSpacesAndTabs:/[ \t\r]/g,_reSpaceAndTab:/[ \t\r]/,_reWords:/\S+/g,fontSize:40,fontWeight:"normal",fontFamily:"Times New Roman",underline:!1,overline:!1,linethrough:!1,textAlign:M,fontStyle:"normal",lineHeight:1.16,textBackgroundColor:"",stroke:null,shadow:null,path:void 0,pathStartOffset:0,pathSide:M,pathAlign:"baseline",charSpacing:0,deltaY:0,direction:"ltr",CACHE_FONT_SIZE:400,MIN_TEXT_WIDTH:2,superscript:{size:.6,baseline:-.35},subscript:{size:.6,baseline:.11},_fontSizeFraction:.222,offsets:{underline:.1,linethrough:-.28167,overline:-.81333},_fontSizeMult:1.13,[ze]:66.667},Je="justify",Qe="justify-left",Ze="justify-right",$e="justify-center";var ts,es,ss;const is=String.raw(ts||(ts=r(["[-+]?(?:d*.d+|d+.?)(?:[eE][-+]?d+)?"],["[-+]?(?:\\d*\\.\\d+|\\d+\\.?)(?:[eE][-+]?\\d+)?"]))),rs=String.raw(es||(es=r(["(?:s*,?s+|s*,s*)"],["(?:\\s*,?\\s+|\\s*,\\s*)"]))),ns="http://www.w3.org/2000/svg",os=new RegExp("(normal|italic)?\\s*(normal|small-caps)?\\s*(normal|bold|bolder|lighter|100|200|300|400|500|600|700|800|900)?\\s*("+is+"(?:px|cm|mm|em|pt|pc|in)*)(?:\\/(normal|"+is+"))?\\s+(.*)"),as={cx:M,x:M,r:"radius",cy:P,y:P,display:"visible",visibility:"visible",transform:"transformMatrix","fill-opacity":"fillOpacity","fill-rule":"fillRule","font-family":"fontFamily","font-size":"fontSize","font-style":"fontStyle","font-weight":"fontWeight","letter-spacing":"charSpacing","paint-order":"paintFirst","stroke-dasharray":"strokeDashArray","stroke-dashoffset":"strokeDashOffset","stroke-linecap":"strokeLineCap","stroke-linejoin":"strokeLineJoin","stroke-miterlimit":"strokeMiterLimit","stroke-opacity":"strokeOpacity","stroke-width":"strokeWidth","text-decoration":"textDecoration","text-anchor":"textAnchor",opacity:"opacity","clip-path":"clipPath","clip-rule":"clipRule","vector-effect":"strokeUniform","image-rendering":"imageSmoothing","text-decoration-thickness":ze},hs="font-size",cs="clip-path",ls=Ve(["path","circle","polygon","polyline","ellipse","rect","line","image","text"]),us=Ve(["symbol","image","marker","pattern","view","svg"]),ds=Ve(["symbol","g","a","svg","clipPath","defs"]),gs=new RegExp(String.raw(ss||(ss=r(["^s*(",")","(",")","(",")","(",")s*$"],["^\\s*(",")","(",")","(",")","(",")\\s*$"])),is,rs,is,rs,is,rs,is)),fs=new ot(1,0),ps=new ot,ms=(t,e)=>t.rotate(e),vs=(t,e)=>new ot(e).subtract(t),ys=t=>t.distanceFrom(ps),_s=(t,e)=>Math.atan2(Ss(t,e),ws(t,e)),xs=t=>_s(fs,t),Cs=t=>t.eq(ps)?t:t.scalarDivide(ys(t)),bs=function(t){let e=!(arguments.length>1&&void 0!==arguments[1])||arguments[1];return Cs(new ot(-t.y,t.x).scalarMultiply(e?1:-1))},Ss=(t,e)=>t.x*e.y-t.y*e.x,ws=(t,e)=>t.x*e.x+t.y*e.y,Ts=(t,e,s)=>{if(t.eq(e)||t.eq(s))return!0;const i=Ss(e,s),r=Ss(e,t),n=Ss(s,t);return i>=0?r>=0&&n<=0:!(r<=0&&n>=0)},Os="(-?\\d+(?:\\.\\d*)?(?:px)?(?:\\s?|$))?",ks=new RegExp("(?:\\s|^)"+Os+Os+"("+is+"?(?:px)?)?(?:\\s?|$)(?:$|\\s)");class Ds{constructor(t){const e="string"==typeof t?Ds.parseShadow(t):t;Object.assign(this,Ds.ownDefaults,e),this.id=ft()}static parseShadow(t){const e=t.trim(),[,s=0,i=0,r=0]=(ks.exec(e)||[]).map((t=>parseFloat(t)||0));return{color:(e.replace(ks,"")||"rgb(0,0,0)").trim(),offsetX:s,offsetY:i,blur:r}}toString(){return[this.offsetX,this.offsetY,this.blur,this.color].join("px ")}toSVG(t){const e=ms(new ot(this.offsetX,this.offsetY),xt(-t.angle)),s=new Ie(this.color);let i=40,r=40;return t.width&&t.height&&(i=100*Vt((Math.abs(e.x)+this.blur)/t.width,o.NUM_FRACTION_DIGITS)+20,r=100*Vt((Math.abs(e.y)+this.blur)/t.height,o.NUM_FRACTION_DIGITS)+20),t.flipX&&(e.x*=-1),t.flipY&&(e.y*=-1),'<filter id="SVGID_'.concat(this.id,'" y="-').concat(r,'%" height="').concat(100+2*r,'%" x="-').concat(i,'%" width="').concat(100+2*i,'%" >\n\t<feGaussianBlur in="SourceAlpha" stdDeviation="').concat(Vt(this.blur?this.blur/2:0,o.NUM_FRACTION_DIGITS),'"></feGaussianBlur>\n\t<feOffset dx="').concat(Vt(e.x,o.NUM_FRACTION_DIGITS),'" dy="').concat(Vt(e.y,o.NUM_FRACTION_DIGITS),'" result="oBlur" ></feOffset>\n\t<feFlood flood-color="').concat(s.toRgb(),'" flood-opacity="').concat(s.getAlpha(),'"/>\n\t<feComposite in2="oBlur" operator="in" />\n\t<feMerge>\n\t\t<feMergeNode></feMergeNode>\n\t\t<feMergeNode in="SourceGraphic"></feMergeNode>\n\t</feMerge>\n</filter>\n')}toObject(){const t={color:this.color,blur:this.blur,offsetX:this.offsetX,offsetY:this.offsetY,affectStroke:this.affectStroke,nonScaling:this.nonScaling,type:this.constructor.type},e=Ds.ownDefaults;return this.includeDefaultValues?t:Wt(t,((t,s)=>t!==e[s]))}static async fromObject(t){return new this(t)}}t(Ds,"ownDefaults",{color:"rgb(0,0,0)",blur:0,offsetX:0,offsetY:0,affectStroke:!1,includeDefaultValues:!0,nonScaling:!1}),t(Ds,"type","shadow"),tt.setClass(Ds,"shadow");const Ms=(t,e,s)=>Math.max(t,Math.min(e,s)),Ps=[P,M,H,N,"flipX","flipY","originX","originY","angle","opacity","globalCompositeOperation","shadow","visible",U,q],Es=[K,J,"strokeWidth","strokeDashArray","width","height","paintFirst","strokeUniform","strokeLineCap","strokeDashOffset","strokeLineJoin","strokeMiterLimit","backgroundColor","clipPath"],As={top:0,left:0,width:0,height:0,angle:0,flipX:!1,flipY:!1,scaleX:1,scaleY:1,minScaleLimit:0,skewX:0,skewY:0,originX:M,originY:P,strokeWidth:1,strokeUniform:!1,padding:0,opacity:1,paintFirst:K,fill:"rgb(0,0,0)",fillRule:"nonzero",stroke:null,strokeDashArray:null,strokeDashOffset:0,strokeLineCap:"butt",strokeLineJoin:"miter",strokeMiterLimit:4,globalCompositeOperation:"source-over",backgroundColor:"",shadow:null,visible:!0,includeDefaultValues:!0,excludeFromExport:!1,objectCaching:!0,clipPath:void 0,inverted:!1,absolutePositioned:!1,centeredRotation:!0,centeredScaling:!1,dirty:!0},js=(t,e,s,i)=>(t<Math.abs(e)?(t=e,i=s/4):i=0===e&&0===t?s/S*Math.asin(1):s/S*Math.asin(e/t),{a:t,c:e,p:s,s:i}),Fs=(t,e,s,i,r)=>t*Math.pow(2,10*(i-=1))*Math.sin((i*r-e)*S/s),Ls=(t,e,s,i)=>-s*Math.cos(t/i*b)+s+e,Rs=(t,e,s,i)=>(t/=i)<1/2.75?s*(7.5625*t*t)+e:t<2/2.75?s*(7.5625*(t-=1.5/2.75)*t+.75)+e:t<2.5/2.75?s*(7.5625*(t-=2.25/2.75)*t+.9375)+e:s*(7.5625*(t-=2.625/2.75)*t+.984375)+e,Is=(t,e,s,i)=>s-Rs(i-t,0,s,i)+e;var Bs=Object.freeze({__proto__:null,defaultEasing:Ls,easeInBack:function(t,e,s,i){let r=arguments.length>4&&void 0!==arguments[4]?arguments[4]:1.70158;return s*(t/=i)*t*((r+1)*t-r)+e},easeInBounce:Is,easeInCirc:(t,e,s,i)=>-s*(Math.sqrt(1-(t/=i)*t)-1)+e,easeInCubic:(t,e,s,i)=>s*(t/i)**3+e,easeInElastic:(t,e,s,i)=>{const r=s;let n=0;if(0===t)return e;if(1===(t/=i))return e+s;n||(n=.3*i);const{a:o,s:a,p:h}=js(r,s,n,1.70158);return-Fs(o,a,h,t,i)+e},easeInExpo:(t,e,s,i)=>0===t?e:s*2**(10*(t/i-1))+e,easeInOutBack:function(t,e,s,i){let r=arguments.length>4&&void 0!==arguments[4]?arguments[4]:1.70158;return(t/=i/2)<1?s/2*(t*t*((1+(r*=1.525))*t-r))+e:s/2*((t-=2)*t*((1+(r*=1.525))*t+r)+2)+e},easeInOutBounce:(t,e,s,i)=>t<i/2?.5*Is(2*t,0,s,i)+e:.5*Rs(2*t-i,0,s,i)+.5*s+e,easeInOutCirc:(t,e,s,i)=>(t/=i/2)<1?-s/2*(Math.sqrt(1-t**2)-1)+e:s/2*(Math.sqrt(1-(t-=2)*t)+1)+e,easeInOutCubic:(t,e,s,i)=>(t/=i/2)<1?s/2*t**3+e:s/2*((t-2)**3+2)+e,easeInOutElastic:(t,e,s,i)=>{const r=s;let n=0;if(0===t)return e;if(2===(t/=i/2))return e+s;n||(n=i*(.3*1.5));const{a:o,s:a,p:h,c:c}=js(r,s,n,1.70158);return t<1?-.5*Fs(o,a,h,t,i)+e:o*Math.pow(2,-10*(t-=1))*Math.sin((t*i-a)*S/h)*.5+c+e},easeInOutExpo:(t,e,s,i)=>0===t?e:t===i?e+s:(t/=i/2)<1?s/2*2**(10*(t-1))+e:s/2*-(2**(-10*--t)+2)+e,easeInOutQuad:(t,e,s,i)=>(t/=i/2)<1?s/2*t**2+e:-s/2*(--t*(t-2)-1)+e,easeInOutQuart:(t,e,s,i)=>(t/=i/2)<1?s/2*t**4+e:-s/2*((t-=2)*t**3-2)+e,easeInOutQuint:(t,e,s,i)=>(t/=i/2)<1?s/2*t**5+e:s/2*((t-2)**5+2)+e,easeInOutSine:(t,e,s,i)=>-s/2*(Math.cos(Math.PI*t/i)-1)+e,easeInQuad:(t,e,s,i)=>s*(t/=i)*t+e,easeInQuart:(t,e,s,i)=>s*(t/=i)*t**3+e,easeInQuint:(t,e,s,i)=>s*(t/i)**5+e,easeInSine:(t,e,s,i)=>-s*Math.cos(t/i*b)+s+e,easeOutBack:function(t,e,s,i){let r=arguments.length>4&&void 0!==arguments[4]?arguments[4]:1.70158;return s*((t=t/i-1)*t*((r+1)*t+r)+1)+e},easeOutBounce:Rs,easeOutCirc:(t,e,s,i)=>s*Math.sqrt(1-(t=t/i-1)*t)+e,easeOutCubic:(t,e,s,i)=>s*((t/i-1)**3+1)+e,easeOutElastic:(t,e,s,i)=>{const r=s;let n=0;if(0===t)return e;if(1===(t/=i))return e+s;n||(n=.3*i);const{a:o,s:a,p:h,c:c}=js(r,s,n,1.70158);return o*2**(-10*t)*Math.sin((t*i-a)*S/h)+c+e},easeOutExpo:(t,e,s,i)=>t===i?e+s:s*-(2**(-10*t/i)+1)+e,easeOutQuad:(t,e,s,i)=>-s*(t/=i)*(t-2)+e,easeOutQuart:(t,e,s,i)=>-s*((t=t/i-1)*t**3-1)+e,easeOutQuint:(t,e,s,i)=>s*((t/i-1)**5+1)+e,easeOutSine:(t,e,s,i)=>s*Math.sin(t/i*b)+e});const Xs=()=>!1;class Ys{constructor(e){let{startValue:s,byValue:i,duration:r=500,delay:n=0,easing:o=Ls,onStart:a=C,onChange:h=C,onComplete:c=C,abort:l=Xs,target:u}=e;t(this,"_state","pending"),t(this,"durationProgress",0),t(this,"valueProgress",0),this.tick=this.tick.bind(this),this.duration=r,this.delay=n,this.easing=o,this._onStart=a,this._onChange=h,this._onComplete=c,this._abort=l,this.target=u,this.startValue=s,this.byValue=i,this.value=this.startValue,this.endValue=Object.freeze(this.calculate(this.duration).value)}get state(){return this._state}isDone(){return"aborted"===this._state||"completed"===this._state}start(){const t=t=>{"pending"===this._state&&(this.startTime=t||+new Date,this._state="running",this._onStart(),this.tick(this.startTime))};this.register(),this.delay>0?setTimeout((()=>ut(t)),this.delay):ut(t)}tick(t){const e=(t||+new Date)-this.startTime,s=Math.min(e,this.duration);this.durationProgress=s/this.duration;const{value:i,valueProgress:r}=this.calculate(s);this.value=Object.freeze(i),this.valueProgress=r,"aborted"!==this._state&&(this._abort(this.value,this.valueProgress,this.durationProgress)?(this._state="aborted",this.unregister()):e>=this.duration?(this.durationProgress=this.valueProgress=1,this._onChange(this.endValue,this.valueProgress,this.durationProgress),this._state="completed",this._onComplete(this.endValue,this.valueProgress,this.durationProgress),this.unregister()):(this._onChange(this.value,this.valueProgress,this.durationProgress),ut(this.tick)))}register(){et.push(this)}unregister(){et.remove(this)}abort(){this._state="aborted",this.unregister()}}const Ws=["startValue","endValue"];class Vs extends Ys{constructor(t){let{startValue:e=0,endValue:r=100}=t;super(s(s({},i(t,Ws)),{},{startValue:e,byValue:r-e}))}calculate(t){const e=this.easing(t,this.startValue,this.byValue,this.duration);return{value:e,valueProgress:Math.abs((e-this.startValue)/this.byValue)}}}const zs=["startValue","endValue"];class Gs extends Ys{constructor(t){let{startValue:e=[0],endValue:r=[100]}=t;super(s(s({},i(t,zs)),{},{startValue:e,byValue:r.map(((t,s)=>t-e[s]))}))}calculate(t){const e=this.startValue.map(((e,s)=>this.easing(t,e,this.byValue[s],this.duration,s)));return{value:e,valueProgress:Math.abs((e[0]-this.startValue[0])/this.byValue[0])}}}const Hs=["startValue","endValue","easing","onChange","onComplete","abort"],Ns=(t,e,s,i)=>e+s*(1-Math.cos(t/i*b)),Us=t=>t&&((e,s,i)=>t(new Ie(e).toRgba(),s,i));class qs extends Ys{constructor(t){let{startValue:e,endValue:r,easing:n=Ns,onChange:o,onComplete:a,abort:h}=t,c=i(t,Hs);const l=new Ie(e).getSource(),u=new Ie(r).getSource();super(s(s({},c),{},{startValue:l,byValue:u.map(((t,e)=>t-l[e])),easing:n,onChange:Us(o),onComplete:Us(a),abort:Us(h)}))}calculate(t){const[e,s,i,r]=this.startValue.map(((e,s)=>this.easing(t,e,this.byValue[s],this.duration,s))),n=[...[e,s,i].map(Math.round),Ms(0,r,1)];return{value:n,valueProgress:n.map(((t,e)=>0!==this.byValue[e]?Math.abs((t-this.startValue[e])/this.byValue[e]):0)).find((t=>0!==t))||0}}}function Ks(t){const e=(t=>Array.isArray(t.startValue)||Array.isArray(t.endValue))(t)?new Gs(t):new Vs(t);return e.start(),e}function Js(t){const e=new qs(t);return e.start(),e}class Qs{constructor(t){this.status=t,this.points=[]}includes(t){return this.points.some((e=>e.eq(t)))}append(){for(var t=arguments.length,e=new Array(t),s=0;s<t;s++)e[s]=arguments[s];return this.points=this.points.concat(e.filter((t=>!this.includes(t)))),this}static isPointContained(t,e,s){let i=arguments.length>3&&void 0!==arguments[3]&&arguments[3];if(e.eq(s))return t.eq(e);if(e.x===s.x)return t.x===e.x&&(i||t.y>=Math.min(e.y,s.y)&&t.y<=Math.max(e.y,s.y));if(e.y===s.y)return t.y===e.y&&(i||t.x>=Math.min(e.x,s.x)&&t.x<=Math.max(e.x,s.x));{const r=vs(e,s),n=vs(e,t).divide(r);return i?Math.abs(n.x)===Math.abs(n.y):n.x===n.y&&n.x>=0&&n.x<=1}}static isPointInPolygon(t,e){const s=new ot(t).setX(Math.min(t.x-1,...e.map((t=>t.x))));let i=0;for(let r=0;r<e.length;r++){const n=this.intersectSegmentSegment(e[r],e[(r+1)%e.length],t,s);if(n.includes(t))return!0;i+=Number("Intersection"===n.status)}return i%2==1}static intersectLineLine(t,e,s,i){let r=!(arguments.length>4&&void 0!==arguments[4])||arguments[4],n=!(arguments.length>5&&void 0!==arguments[5])||arguments[5];const o=e.x-t.x,a=e.y-t.y,h=i.x-s.x,c=i.y-s.y,l=t.x-s.x,u=t.y-s.y,d=h*u-c*l,g=o*u-a*l,f=c*o-h*a;if(0!==f){const e=d/f,s=g/f;return(r||0<=e&&e<=1)&&(n||0<=s&&s<=1)?new Qs("Intersection").append(new ot(t.x+e*o,t.y+e*a)):new Qs}if(0===d||0===g){const o=r||n||Qs.isPointContained(t,s,i)||Qs.isPointContained(e,s,i)||Qs.isPointContained(s,t,e)||Qs.isPointContained(i,t,e);return new Qs(o?"Coincident":void 0)}return new Qs("Parallel")}static intersectSegmentLine(t,e,s,i){return Qs.intersectLineLine(t,e,s,i,!1,!0)}static intersectSegmentSegment(t,e,s,i){return Qs.intersectLineLine(t,e,s,i,!1,!1)}static intersectLinePolygon(t,e,s){let i=!(arguments.length>3&&void 0!==arguments[3])||arguments[3];const r=new Qs,n=s.length;for(let o,a,h,c=0;c<n;c++){if(o=s[c],a=s[(c+1)%n],h=Qs.intersectLineLine(t,e,o,a,i,!1),"Coincident"===h.status)return h;r.append(...h.points)}return r.points.length>0&&(r.status="Intersection"),r}static intersectSegmentPolygon(t,e,s){return Qs.intersectLinePolygon(t,e,s,!1)}static intersectPolygonPolygon(t,e){const s=new Qs,i=t.length,r=[];for(let n=0;n<i;n++){const o=t[n],a=t[(n+1)%i],h=Qs.intersectSegmentPolygon(o,a,e);"Coincident"===h.status?(r.push(h),s.append(o,a)):s.append(...h.points)}return r.length>0&&r.length===t.length?new Qs("Coincident"):(s.points.length>0&&(s.status="Intersection"),s)}static intersectPolygonRectangle(t,e,s){const i=e.min(s),r=e.max(s),n=new ot(r.x,i.y),o=new ot(i.x,r.y);return Qs.intersectPolygonPolygon(t,[i,n,r,o])}}class Zs extends lt{getX(){return this.getXY().x}setX(t){this.setXY(this.getXY().setX(t))}getY(){return this.getXY().y}setY(t){this.setXY(this.getXY().setY(t))}getRelativeX(){return this.left}setRelativeX(t){this.left=t}getRelativeY(){return this.top}setRelativeY(t){this.top=t}getXY(){const t=this.getRelativeXY();return this.group?St(t,this.group.calcTransformMatrix()):t}setXY(t,e,s){this.group&&(t=St(t,wt(this.group.calcTransformMatrix()))),this.setRelativeXY(t,e,s)}getRelativeXY(){return new ot(this.left,this.top)}setRelativeXY(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:this.originX,s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:this.originY;this.setPositionByOrigin(t,e,s)}isStrokeAccountedForInDimensions(){return!1}getCoords(){const{tl:t,tr:e,br:s,bl:i}=this.aCoords||(this.aCoords=this.calcACoords()),r=[t,e,s,i];if(this.group){const t=this.group.calcTransformMatrix();return r.map((e=>St(e,t)))}return r}intersectsWithRect(t,e){return"Intersection"===Qs.intersectPolygonRectangle(this.getCoords(),t,e).status}intersectsWithObject(t){const e=Qs.intersectPolygonPolygon(this.getCoords(),t.getCoords());return"Intersection"===e.status||"Coincident"===e.status||t.isContainedWithinObject(this)||this.isContainedWithinObject(t)}isContainedWithinObject(t){return this.getCoords().every((e=>t.containsPoint(e)))}isContainedWithinRect(t,e){const{left:s,top:i,width:r,height:n}=this.getBoundingRect();return s>=t.x&&s+r<=e.x&&i>=t.y&&i+n<=e.y}isOverlapping(t){return this.intersectsWithObject(t)||this.isContainedWithinObject(t)||t.isContainedWithinObject(this)}containsPoint(t){return Qs.isPointInPolygon(t,this.getCoords())}isOnScreen(){if(!this.canvas)return!1;const{tl:t,br:e}=this.canvas.vptCoords;return!!this.getCoords().some((s=>s.x<=e.x&&s.x>=t.x&&s.y<=e.y&&s.y>=t.y))||(!!this.intersectsWithRect(t,e)||this.containsPoint(t.midPointFrom(e)))}isPartiallyOnScreen(){if(!this.canvas)return!1;const{tl:t,br:e}=this.canvas.vptCoords;if(this.intersectsWithRect(t,e))return!0;return this.getCoords().every((s=>(s.x>=e.x||s.x<=t.x)&&(s.y>=e.y||s.y<=t.y)))&&this.containsPoint(t.midPointFrom(e))}getBoundingRect(){return he(this.getCoords())}getScaledWidth(){return this._getTransformedDimensions().x}getScaledHeight(){return this._getTransformedDimensions().y}scale(t){this._set(H,t),this._set(N,t),this.setCoords()}scaleToWidth(t){const e=this.getBoundingRect().width/this.getScaledWidth();return this.scale(t/this.width/e)}scaleToHeight(t){const e=this.getBoundingRect().height/this.getScaledHeight();return this.scale(t/this.height/e)}getCanvasRetinaScaling(){var t;return(null===(t=this.canvas)||void 0===t?void 0:t.getRetinaScaling())||1}getTotalAngle(){return this.group?Ct(kt(this.calcTransformMatrix())):this.angle}getViewportTransform(){var t;return(null===(t=this.canvas)||void 0===t?void 0:t.viewportTransform)||T.concat()}calcACoords(){const t=Pt({angle:this.angle}),{x:e,y:s}=this.getRelativeCenterPoint(),i=Mt(e,s),r=Tt(i,t),n=this._getTransformedDimensions(),o=n.x/2,a=n.y/2;return{tl:St({x:-o,y:-a},r),tr:St({x:o,y:-a},r),bl:St({x:-o,y:a},r),br:St({x:o,y:a},r)}}setCoords(){this.aCoords=this.calcACoords()}transformMatrixKey(){let t=arguments.length>0&&void 0!==arguments[0]&&arguments[0],e=[];return!t&&this.group&&(e=this.group.transformMatrixKey(t)),e.push(this.top,this.left,this.width,this.height,this.scaleX,this.scaleY,this.angle,this.strokeWidth,this.skewX,this.skewY,+this.flipX,+this.flipY,Ce(this.originX),Ce(this.originY)),e}calcTransformMatrix(){let t=arguments.length>0&&void 0!==arguments[0]&&arguments[0],e=this.calcOwnMatrix();if(t||!this.group)return e;const s=this.transformMatrixKey(t),i=this.matrixCache;return i&&i.key.every(((t,e)=>t===s[e]))?i.value:(this.group&&(e=Tt(this.group.calcTransformMatrix(!1),e)),this.matrixCache={key:s,value:e},e)}calcOwnMatrix(){const t=this.transformMatrixKey(!0),e=this.ownMatrixCache;if(e&&e.key===t)return e.value;const s=this.getRelativeCenterPoint(),i={angle:this.angle,translateX:s.x,translateY:s.y,scaleX:this.scaleX,scaleY:this.scaleY,skewX:this.skewX,skewY:this.skewY,flipX:this.flipX,flipY:this.flipY},r=Rt(i);return this.ownMatrixCache={key:t,value:r},r}_getNonTransformedDimensions(){return new ot(this.width,this.height).scalarAdd(this.strokeWidth)}_calculateCurrentDimensions(t){return this._getTransformedDimensions(t).transform(this.getViewportTransform(),!0).scalarAdd(2*this.padding)}_getTransformedDimensions(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};const e=s({scaleX:this.scaleX,scaleY:this.scaleY,skewX:this.skewX,skewY:this.skewY,width:this.width,height:this.height,strokeWidth:this.strokeWidth},t),i=e.strokeWidth;let r=i,n=0;this.strokeUniform&&(r=0,n=i);const o=e.width+r,a=e.height+r;let h;return h=0===e.skewX&&0===e.skewY?new ot(o*e.scaleX,a*e.scaleY):fe(o,a,Lt(e)),h.scalarAdd(n)}translateToGivenOrigin(t,e,s,i,r){let n=t.x,o=t.y;const a=Ce(i)-Ce(e),h=Ce(r)-Ce(s);if(a||h){const t=this._getTransformedDimensions();n+=a*t.x,o+=h*t.y}return new ot(n,o)}translateToCenterPoint(t,e,s){if(e===D&&s===D)return t;const i=this.translateToGivenOrigin(t,e,s,D,D);return this.angle?i.rotate(xt(this.angle),t):i}translateToOriginPoint(t,e,s){const i=this.translateToGivenOrigin(t,D,D,e,s);return this.angle?i.rotate(xt(this.angle),t):i}getCenterPoint(){const t=this.getRelativeCenterPoint();return this.group?St(t,this.group.calcTransformMatrix()):t}getRelativeCenterPoint(){return this.translateToCenterPoint(new ot(this.left,this.top),this.originX,this.originY)}getPointByOrigin(t,e){return this.translateToOriginPoint(this.getRelativeCenterPoint(),t,e)}setPositionByOrigin(t,e,s){const i=this.translateToCenterPoint(t,e,s),r=this.translateToOriginPoint(i,this.originX,this.originY);this.set({left:r.x,top:r.y})}_getLeftTopCoords(){return this.translateToOriginPoint(this.getRelativeCenterPoint(),M,P)}}const $s=["type"],ti=["extraParam"];let ei=class e extends Zs{static getDefaults(){return e.ownDefaults}get type(){const t=this.constructor.type;return"FabricObject"===t?"object":t.toLowerCase()}set type(t){a("warn","Setting type has no effect",t)}constructor(s){super(),t(this,"_cacheContext",null),Object.assign(this,e.ownDefaults),this.setOptions(s)}_createCacheCanvas(){this._cacheCanvas=pt(),this._cacheContext=this._cacheCanvas.getContext("2d"),this._updateCacheCanvas(),this.dirty=!0}_limitCacheSize(t){const e=t.width,s=t.height,i=o.maxCacheSideLimit,r=o.minCacheSideLimit;if(e<=i&&s<=i&&e*s<=o.perfLimitSizeTotal)return e<r&&(t.width=r),s<r&&(t.height=r),t;const n=e/s,[a,h]=_.limitDimsByArea(n),c=Ms(r,a,i),l=Ms(r,h,i);return e>c&&(t.zoomX/=e/c,t.width=c,t.capped=!0),s>l&&(t.zoomY/=s/l,t.height=l,t.capped=!0),t}_getCacheCanvasDimensions(){const t=this.getTotalObjectScaling(),e=this._getTransformedDimensions({skewX:0,skewY:0}),s=e.x*t.x/this.scaleX,i=e.y*t.y/this.scaleY;return{width:Math.ceil(s+2),height:Math.ceil(i+2),zoomX:t.x,zoomY:t.y,x:s,y:i}}_updateCacheCanvas(){const t=this._cacheCanvas,e=this._cacheContext,{width:s,height:i,zoomX:r,zoomY:n,x:o,y:a}=this._limitCacheSize(this._getCacheCanvasDimensions()),h=s!==t.width||i!==t.height,c=this.zoomX!==r||this.zoomY!==n;if(!t||!e)return!1;if(h||c){s!==t.width||i!==t.height?(t.width=s,t.height=i):(e.setTransform(1,0,0,1,0,0),e.clearRect(0,0,t.width,t.height));const h=o/2,c=a/2;return this.cacheTranslationX=Math.round(t.width/2-h)+h,this.cacheTranslationY=Math.round(t.height/2-c)+c,e.translate(this.cacheTranslationX,this.cacheTranslationY),e.scale(r,n),this.zoomX=r,this.zoomY=n,!0}return!1}setOptions(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};this._setOptions(t)}transform(t){const e=this.group&&!this.group._transformDone||this.group&&this.canvas&&t===this.canvas.contextTop,s=this.calcTransformMatrix(!e);t.transform(s[0],s[1],s[2],s[3],s[4],s[5])}getObjectScaling(){if(!this.group)return new ot(Math.abs(this.scaleX),Math.abs(this.scaleY));const t=Dt(this.calcTransformMatrix());return new ot(Math.abs(t.scaleX),Math.abs(t.scaleY))}getTotalObjectScaling(){const t=this.getObjectScaling();if(this.canvas){const e=this.canvas.getZoom(),s=this.getCanvasRetinaScaling();return t.scalarMultiply(e*s)}return t}getObjectOpacity(){let t=this.opacity;return this.group&&(t*=this.group.getObjectOpacity()),t}_constrainScale(t){return Math.abs(t)<this.minScaleLimit?t<0?-this.minScaleLimit:this.minScaleLimit:0===t?1e-4:t}_set(t,e){t!==H&&t!==N||(e=this._constrainScale(e)),t===H&&e<0?(this.flipX=!this.flipX,e*=-1):"scaleY"===t&&e<0?(this.flipY=!this.flipY,e*=-1):"shadow"!==t||!e||e instanceof Ds||(e=new Ds(e));const s=this[t]!==e;return this[t]=e,s&&this.constructor.cacheProperties.includes(t)&&(this.dirty=!0),this.parent&&(this.dirty||s&&this.constructor.stateProperties.includes(t))&&this.parent._set("dirty",!0),this}isNotVisible(){return 0===this.opacity||!this.width&&!this.height&&0===this.strokeWidth||!this.visible}render(t){this.isNotVisible()||this.canvas&&this.canvas.skipOffscreen&&!this.group&&!this.isOnScreen()||(t.save(),this._setupCompositeOperation(t),this.drawSelectionBackground(t),this.transform(t),this._setOpacity(t),this._setShadow(t),this.shouldCache()?(this.renderCache(),this.drawCacheOnCanvas(t)):(this._removeCacheCanvas(),this.drawObject(t,!1,{}),this.dirty=!1),t.restore())}drawSelectionBackground(t){}renderCache(t){if(t=t||{},this._cacheCanvas&&this._cacheContext||this._createCacheCanvas(),this.isCacheDirty()&&this._cacheContext){const{zoomX:e,zoomY:s,cacheTranslationX:i,cacheTranslationY:r}=this,{width:n,height:o}=this._cacheCanvas;this.drawObject(this._cacheContext,t.forClipping,{zoomX:e,zoomY:s,cacheTranslationX:i,cacheTranslationY:r,width:n,height:o,parentClipPaths:[]}),this.dirty=!1}}_removeCacheCanvas(){this._cacheCanvas=void 0,this._cacheContext=null}hasStroke(){return this.stroke&&"transparent"!==this.stroke&&0!==this.strokeWidth}hasFill(){return this.fill&&"transparent"!==this.fill}needsItsOwnCache(){return!!(this.paintFirst===J&&this.hasFill()&&this.hasStroke()&&this.shadow)||!!this.clipPath}shouldCache(){return this.ownCaching=this.objectCaching&&(!this.parent||!this.parent.isOnACache())||this.needsItsOwnCache(),this.ownCaching}willDrawShadow(){return!!this.shadow&&(0!==this.shadow.offsetX||0!==this.shadow.offsetY)}drawClipPathOnCache(t,e,s){t.save(),e.inverted?t.globalCompositeOperation="destination-out":t.globalCompositeOperation="destination-in",t.setTransform(1,0,0,1,0,0),t.drawImage(s,0,0),t.restore()}drawObject(t,e,s){const i=this.fill,r=this.stroke;e?(this.fill="black",this.stroke="",this._setClippingProperties(t)):this._renderBackground(t),this._render(t),this._drawClipPath(t,this.clipPath,s),this.fill=i,this.stroke=r}createClipPathLayer(t,e){const s=vt(e),i=s.getContext("2d");if(i.translate(e.cacheTranslationX,e.cacheTranslationY),i.scale(e.zoomX,e.zoomY),t._cacheCanvas=s,e.parentClipPaths.forEach((t=>{t.transform(i)})),e.parentClipPaths.push(t),t.absolutePositioned){const t=wt(this.calcTransformMatrix());i.transform(t[0],t[1],t[2],t[3],t[4],t[5])}return t.transform(i),t.drawObject(i,!0,e),s}_drawClipPath(t,e,s){if(!e)return;e._transformDone=!0;const i=this.createClipPathLayer(e,s);this.drawClipPathOnCache(t,e,i)}drawCacheOnCanvas(t){t.scale(1/this.zoomX,1/this.zoomY),t.drawImage(this._cacheCanvas,-this.cacheTranslationX,-this.cacheTranslationY)}isCacheDirty(){let t=arguments.length>0&&void 0!==arguments[0]&&arguments[0];if(this.isNotVisible())return!1;const e=this._cacheCanvas,s=this._cacheContext;return!(!e||!s||t||!this._updateCacheCanvas())||!!(this.dirty||this.clipPath&&this.clipPath.absolutePositioned)&&(e&&s&&!t&&(s.save(),s.setTransform(1,0,0,1,0,0),s.clearRect(0,0,e.width,e.height),s.restore()),!0)}_renderBackground(t){if(!this.backgroundColor)return;const e=this._getNonTransformedDimensions();t.fillStyle=this.backgroundColor,t.fillRect(-e.x/2,-e.y/2,e.x,e.y),this._removeShadow(t)}_setOpacity(t){this.group&&!this.group._transformDone?t.globalAlpha=this.getObjectOpacity():t.globalAlpha*=this.opacity}_setStrokeStyles(t,e){const s=e.stroke;s&&(t.lineWidth=e.strokeWidth,t.lineCap=e.strokeLineCap,t.lineDashOffset=e.strokeDashOffset,t.lineJoin=e.strokeLineJoin,t.miterLimit=e.strokeMiterLimit,Gt(s)?"percentage"===s.gradientUnits||s.gradientTransform||s.patternTransform?this._applyPatternForTransformedGradient(t,s):(t.strokeStyle=s.toLive(t),this._applyPatternGradientTransform(t,s)):t.strokeStyle=e.stroke)}_setFillStyles(t,e){let{fill:s}=e;s&&(Gt(s)?(t.fillStyle=s.toLive(t),this._applyPatternGradientTransform(t,s)):t.fillStyle=s)}_setClippingProperties(t){t.globalAlpha=1,t.strokeStyle="transparent",t.fillStyle="#000000"}_setLineDash(t,e){e&&0!==e.length&&t.setLineDash(e)}_setShadow(t){if(!this.shadow)return;const e=this.shadow,s=this.canvas,i=this.getCanvasRetinaScaling(),[r,,,n]=(null==s?void 0:s.viewportTransform)||T,a=r*i,h=n*i,c=e.nonScaling?new ot(1,1):this.getObjectScaling();t.shadowColor=e.color,t.shadowBlur=e.blur*o.browserShadowBlurConstant*(a+h)*(c.x+c.y)/4,t.shadowOffsetX=e.offsetX*a*c.x,t.shadowOffsetY=e.offsetY*h*c.y}_removeShadow(t){this.shadow&&(t.shadowColor="",t.shadowBlur=t.shadowOffsetX=t.shadowOffsetY=0)}_applyPatternGradientTransform(t,e){if(!Gt(e))return{offsetX:0,offsetY:0};const s=e.gradientTransform||e.patternTransform,i=-this.width/2+e.offsetX||0,r=-this.height/2+e.offsetY||0;return"percentage"===e.gradientUnits?t.transform(this.width,0,0,this.height,i,r):t.transform(1,0,0,1,i,r),s&&t.transform(s[0],s[1],s[2],s[3],s[4],s[5]),{offsetX:i,offsetY:r}}_renderPaintInOrder(t){this.paintFirst===J?(this._renderStroke(t),this._renderFill(t)):(this._renderFill(t),this._renderStroke(t))}_render(t){}_renderFill(t){this.fill&&(t.save(),this._setFillStyles(t,this),"evenodd"===this.fillRule?t.fill("evenodd"):t.fill(),t.restore())}_renderStroke(t){if(this.stroke&&0!==this.strokeWidth){if(this.shadow&&!this.shadow.affectStroke&&this._removeShadow(t),t.save(),this.strokeUniform){const e=this.getObjectScaling();t.scale(1/e.x,1/e.y)}this._setLineDash(t,this.strokeDashArray),this._setStrokeStyles(t,this),t.stroke(),t.restore()}}_applyPatternForTransformedGradient(t,e){var s;const i=this._limitCacheSize(this._getCacheCanvasDimensions()),r=this.getCanvasRetinaScaling(),n=i.x/this.scaleX/r,o=i.y/this.scaleY/r,a=vt({width:Math.ceil(n),height:Math.ceil(o)}),h=a.getContext("2d");h&&(h.beginPath(),h.moveTo(0,0),h.lineTo(n,0),h.lineTo(n,o),h.lineTo(0,o),h.closePath(),h.translate(n/2,o/2),h.scale(i.zoomX/this.scaleX/r,i.zoomY/this.scaleY/r),this._applyPatternGradientTransform(h,e),h.fillStyle=e.toLive(t),h.fill(),t.translate(-this.width/2-this.strokeWidth/2,-this.height/2-this.strokeWidth/2),t.scale(r*this.scaleX/i.zoomX,r*this.scaleY/i.zoomY),t.strokeStyle=null!==(s=h.createPattern(a,"no-repeat"))&&void 0!==s?s:"")}_findCenterFromElement(){return new ot(this.left+this.width/2,this.top+this.height/2)}clone(t){const e=this.toObject(t);return this.constructor.fromObject(e)}cloneAsImage(t){const e=this.toCanvasElement(t);return new(tt.getClass("image"))(e)}toCanvasElement(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};const e=ge(this),s=this.group,i=this.shadow,r=Math.abs,n=t.enableRetinaScaling?y():1,o=(t.multiplier||1)*n,a=t.canvasProvider||(t=>new ie(t,{enableRetinaScaling:!1,renderOnAddRemove:!1,skipOffscreen:!1}));delete this.group,t.withoutTransform&&de(this),t.withoutShadow&&(this.shadow=null),t.viewportTransform&&ye(this,this.getViewportTransform()),this.setCoords();const h=pt(),c=this.getBoundingRect(),l=this.shadow,u=new ot;if(l){const t=l.blur,e=l.nonScaling?new ot(1,1):this.getObjectScaling();u.x=2*Math.round(r(l.offsetX)+t)*r(e.x),u.y=2*Math.round(r(l.offsetY)+t)*r(e.y)}const d=c.width+u.x,g=c.height+u.y;h.width=Math.ceil(d),h.height=Math.ceil(g);const f=a(h);"jpeg"===t.format&&(f.backgroundColor="#fff"),this.setPositionByOrigin(new ot(f.width/2,f.height/2),D,D);const p=this.canvas;f._objects=[this],this.set("canvas",f),this.setCoords();const m=f.toCanvasElement(o||1,t);return this.set("canvas",p),this.shadow=i,s&&(this.group=s),this.set(e),this.setCoords(),f._objects=[],f.destroy(),m}toDataURL(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};return yt(this.toCanvasElement(t),t.format||"png",t.quality||1)}toBlob(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};return _t(this.toCanvasElement(t),t.format||"png",t.quality||1)}isType(){for(var t=arguments.length,e=new Array(t),s=0;s<t;s++)e[s]=arguments[s];return e.includes(this.constructor.type)||e.includes(this.type)}complexity(){return 1}toJSON(){return this.toObject()}rotate(t){const{centeredRotation:e,originX:s,originY:i}=this;if(e){const{x:t,y:e}=this.getRelativeCenterPoint();this.originX=D,this.originY=D,this.left=t,this.top=e}if(this.set("angle",t),e){const{x:t,y:e}=this.translateToOriginPoint(this.getRelativeCenterPoint(),s,i);this.left=t,this.top=e,this.originX=s,this.originY=i}}setOnGroup(){}_setupCompositeOperation(t){this.globalCompositeOperation&&(t.globalCompositeOperation=this.globalCompositeOperation)}dispose(){et.cancelByTarget(this),this.off(),this._set("canvas",void 0),this._cacheCanvas&&p().dispose(this._cacheCanvas),this._cacheCanvas=void 0,this._cacheContext=null}animate(t,e){return Object.entries(t).reduce(((t,s)=>{let[i,r]=s;return t[i]=this._animate(i,r,e),t}),{})}_animate(t,e){let i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{};const r=t.split("."),n=this.constructor.colorProperties.includes(r[r.length-1]),{abort:o,startValue:a,onChange:h,onComplete:c}=i,l=s(s({},i),{},{target:this,startValue:null!=a?a:r.reduce(((t,e)=>t[e]),this),endValue:e,abort:null==o?void 0:o.bind(this),onChange:(t,e,s)=>{r.reduce(((e,s,i)=>(i===r.length-1&&(e[s]=t),e[s])),this),h&&h(t,e,s)},onComplete:(t,e,s)=>{this.setCoords(),c&&c(t,e,s)}});return n?Js(l):Ks(l)}isDescendantOf(t){const{parent:e,group:s}=this;return e===t||s===t||!!e&&e.isDescendantOf(t)||!!s&&s!==e&&s.isDescendantOf(t)}getAncestors(){const t=[];let e=this;do{e=e.parent,e&&t.push(e)}while(e);return t}findCommonAncestors(t){if(this===t)return{fork:[],otherFork:[],common:[this,...this.getAncestors()]};const e=this.getAncestors(),s=t.getAncestors();if(0===e.length&&s.length>0&&this===s[s.length-1])return{fork:[],otherFork:[t,...s.slice(0,s.length-1)],common:[this]};for(let i,r=0;r<e.length;r++){if(i=e[r],i===t)return{fork:[this,...e.slice(0,r)],otherFork:[],common:e.slice(r)};for(let n=0;n<s.length;n++){if(this===s[n])return{fork:[],otherFork:[t,...s.slice(0,n)],common:[this,...e]};if(i===s[n])return{fork:[this,...e.slice(0,r)],otherFork:[t,...s.slice(0,n)],common:e.slice(r)}}}return{fork:[this,...e],otherFork:[t,...s],common:[]}}hasCommonAncestors(t){const e=this.findCommonAncestors(t);return e&&!!e.common.length}isInFrontOf(t){if(this===t)return;const e=this.findCommonAncestors(t);if(e.fork.includes(t))return!0;if(e.otherFork.includes(this))return!1;const s=e.common[0]||this.canvas;if(!s)return;const i=e.fork.pop(),r=e.otherFork.pop(),n=s._objects.indexOf(i),o=s._objects.indexOf(r);return n>-1&&n>o}toObject(){const t=(arguments.length>0&&void 0!==arguments[0]?arguments[0]:[]).concat(e.customProperties,this.constructor.customProperties||[]);let i;const r=o.NUM_FRACTION_DIGITS,{clipPath:n,fill:a,stroke:h,shadow:c,strokeDashArray:l,left:u,top:d,originX:g,originY:f,width:p,height:m,strokeWidth:v,strokeLineCap:y,strokeDashOffset:_,strokeLineJoin:C,strokeUniform:b,strokeMiterLimit:S,scaleX:w,scaleY:T,angle:O,flipX:k,flipY:D,opacity:M,visible:P,backgroundColor:E,fillRule:A,paintFirst:j,globalCompositeOperation:F,skewX:L,skewY:R}=this;n&&!n.excludeFromExport&&(i=n.toObject(t.concat("inverted","absolutePositioned")));const I=t=>Vt(t,r),B=s(s({},Yt(this,t)),{},{type:this.constructor.type,version:x,originX:g,originY:f,left:I(u),top:I(d),width:I(p),height:I(m),fill:Ht(a)?a.toObject():a,stroke:Ht(h)?h.toObject():h,strokeWidth:I(v),strokeDashArray:l?l.concat():l,strokeLineCap:y,strokeDashOffset:_,strokeLineJoin:C,strokeUniform:b,strokeMiterLimit:I(S),scaleX:I(w),scaleY:I(T),angle:I(O),flipX:k,flipY:D,opacity:I(M),shadow:c?c.toObject():c,visible:P,backgroundColor:E,fillRule:A,paintFirst:j,globalCompositeOperation:F,skewX:I(L),skewY:I(R)},i?{clipPath:i}:null);return this.includeDefaultValues?B:this._removeDefaultValues(B)}toDatalessObject(t){return this.toObject(t)}_removeDefaultValues(t){const e=this.constructor.getDefaults(),s=Object.keys(e).length>0?e:Object.getPrototypeOf(this);return Wt(t,((t,e)=>{if(e===M||e===P||"type"===e)return!0;const i=s[e];return t!==i&&!(Array.isArray(t)&&Array.isArray(i)&&0===t.length&&0===i.length)}))}toString(){return"#<".concat(this.constructor.type,">")}static _fromObject(t){let e=i(t,$s),s=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},{extraParam:r}=s,n=i(s,ti);return Xt(e,n).then((t=>r?(delete t[r],new this(e[r],t)):new this(t)))}static fromObject(t,e){return this._fromObject(t,e)}};t(ei,"stateProperties",Ps),t(ei,"cacheProperties",Es),t(ei,"ownDefaults",As),t(ei,"type","FabricObject"),t(ei,"colorProperties",[K,J,"backgroundColor"]),t(ei,"customProperties",[]),tt.setClass(ei),tt.setClass(ei,"object");const si=(t,e,i)=>(r,n,o,a)=>{const h=e(r,n,o,a);return h&&_e(t,s(s({},Oe(r,n,o,a)),i)),h};function ii(t){return(e,s,i,r)=>{const{target:n,originX:o,originY:a}=s,h=n.getRelativeCenterPoint(),c=n.translateToOriginPoint(h,o,a),l=t(e,s,i,r);return n.setPositionByOrigin(c,s.originX,s.originY),l}}const ri=si(Y,ii(((t,e,s,i)=>{const r=De(e,e.originX,e.originY,s,i);if(Ce(e.originX)===Ce(D)||Ce(e.originX)===Ce(A)&&r.x<0||Ce(e.originX)===Ce(M)&&r.x>0){const{target:t}=e,s=t.strokeWidth/(t.strokeUniform?t.scaleX:1),i=Se(e)?2:1,n=t.width,o=Math.abs(r.x*i/t.scaleX)-s;return t.set("width",Math.max(o,1)),n!==t.width}return!1})));function ni(t,e,s,i,r){i=i||{};const n=this.sizeX||i.cornerSize||r.cornerSize,o=this.sizeY||i.cornerSize||r.cornerSize,a=void 0!==i.transparentCorners?i.transparentCorners:r.transparentCorners,h=a?J:K,c=!a&&(i.cornerStrokeColor||r.cornerStrokeColor);let l,u=e,d=s;t.save(),t.fillStyle=i.cornerColor||r.cornerColor||"",t.strokeStyle=i.cornerStrokeColor||r.cornerStrokeColor||"",n>o?(l=n,t.scale(1,o/n),d=s*n/o):o>n?(l=o,t.scale(n/o,1),u=e*o/n):l=n,t.beginPath(),t.arc(u,d,l/2,0,S,!1),t[h](),c&&t.stroke(),t.restore()}function oi(t,e,s,i,r){i=i||{};const n=this.sizeX||i.cornerSize||r.cornerSize,o=this.sizeY||i.cornerSize||r.cornerSize,a=void 0!==i.transparentCorners?i.transparentCorners:r.transparentCorners,h=a?J:K,c=!a&&(i.cornerStrokeColor||r.cornerStrokeColor),l=n/2,u=o/2;t.save(),t.fillStyle=i.cornerColor||r.cornerColor||"",t.strokeStyle=i.cornerStrokeColor||r.cornerStrokeColor||"",t.translate(e,s);const d=r.getTotalAngle();t.rotate(xt(d)),t["".concat(h,"Rect")](-l,-u,n,o),c&&t.strokeRect(-l,-u,n,o),t.restore()}class ai{constructor(e){t(this,"visible",!0),t(this,"actionName",G),t(this,"angle",0),t(this,"x",0),t(this,"y",0),t(this,"offsetX",0),t(this,"offsetY",0),t(this,"sizeX",0),t(this,"sizeY",0),t(this,"touchSizeX",0),t(this,"touchSizeY",0),t(this,"cursorStyle","crosshair"),t(this,"withConnection",!1),Object.assign(this,e)}shouldActivate(t,e,s,i){var r;let{tl:n,tr:o,br:a,bl:h}=i;return(null===(r=e.canvas)||void 0===r?void 0:r.getActiveObject())===e&&e.isControlVisible(t)&&Qs.isPointInPolygon(s,[n,o,a,h])}getActionHandler(t,e,s){return this.actionHandler}getMouseDownHandler(t,e,s){return this.mouseDownHandler}getMouseUpHandler(t,e,s){return this.mouseUpHandler}cursorStyleHandler(t,e,s){return e.cursorStyle}getActionName(t,e,s){return e.actionName}getVisibility(t,e){var s,i;return null!==(s=null===(i=t._controlsVisibility)||void 0===i?void 0:i[e])&&void 0!==s?s:this.visible}setVisibility(t,e,s){this.visible=t}positionHandler(t,e,s,i){return new ot(this.x*t.x+this.offsetX,this.y*t.y+this.offsetY).transform(e)}calcCornerCoords(t,e,s,i,r,n){const o=Ot([Mt(s,i),Pt({angle:t}),Et((r?this.touchSizeX:this.sizeX)||e,(r?this.touchSizeY:this.sizeY)||e)]);return{tl:new ot(-.5,-.5).transform(o),tr:new ot(.5,-.5).transform(o),br:new ot(.5,.5).transform(o),bl:new ot(-.5,.5).transform(o)}}render(t,e,s,i,r){if("circle"===((i=i||{}).cornerStyle||r.cornerStyle))ni.call(this,t,e,s,i,r);else oi.call(this,t,e,s,i,r)}}const hi=(t,e,s)=>s.lockRotation?be:e.cursorStyle,ci=si(I,ii(((t,e,s,i)=>{let{target:r,ex:n,ey:o,theta:a,originX:h,originY:c}=e;const l=r.translateToOriginPoint(r.getRelativeCenterPoint(),h,c);if(Te(r,"lockRotation"))return!1;const u=Math.atan2(o-l.y,n-l.x),d=Math.atan2(i-l.y,s-l.x);let g=Ct(d-u+a);if(r.snapAngle&&r.snapAngle>0){const t=r.snapAngle,e=r.snapThreshold||t,s=Math.ceil(g/t)*t,i=Math.floor(g/t)*t;Math.abs(g-i)<e?g=i:Math.abs(g-s)<e&&(g=s)}g<0&&(g=360+g),g%=360;const f=r.angle!==g;return r.angle=g,f})));function li(t,e){const s=e.canvas,i=t[s.uniScaleKey];return s.uniformScaling&&!i||!s.uniformScaling&&i}function ui(t,e,s){const i=Te(t,"lockScalingX"),r=Te(t,"lockScalingY");if(i&&r)return!0;if(!e&&(i||r)&&s)return!0;if(i&&"x"===e)return!0;if(r&&"y"===e)return!0;const{width:n,height:o,strokeWidth:a}=t;return 0===n&&0===a&&"y"!==e||0===o&&0===a&&"x"!==e}const di=["e","se","s","sw","w","nw","n","ne","e"],gi=(t,e,s)=>{const i=li(t,s);if(ui(s,0!==e.x&&0===e.y?"x":0===e.x&&0!==e.y?"y":"",i))return be;const r=ke(s,e);return"".concat(di[r],"-resize")};function fi(t,e,s,i){let r=arguments.length>4&&void 0!==arguments[4]?arguments[4]:{};const n=e.target,o=r.by,a=li(t,n);let h,c,l,u,d,g;if(ui(n,o,a))return!1;if(e.gestureScale)c=e.scaleX*e.gestureScale,l=e.scaleY*e.gestureScale;else{if(h=De(e,e.originX,e.originY,s,i),d="y"!==o?Math.sign(h.x||e.signX||1):1,g="x"!==o?Math.sign(h.y||e.signY||1):1,e.signX||(e.signX=d),e.signY||(e.signY=g),Te(n,"lockScalingFlip")&&(e.signX!==d||e.signY!==g))return!1;if(u=n._getTransformedDimensions(),a&&!o){const t=Math.abs(h.x)+Math.abs(h.y),{original:s}=e,i=t/(Math.abs(u.x*s.scaleX/n.scaleX)+Math.abs(u.y*s.scaleY/n.scaleY));c=s.scaleX*i,l=s.scaleY*i}else c=Math.abs(h.x*n.scaleX/u.x),l=Math.abs(h.y*n.scaleY/u.y);Se(e)&&(c*=2,l*=2),e.signX!==d&&"y"!==o&&(e.originX=we(e.originX),c*=-1,e.signX=d),e.signY!==g&&"x"!==o&&(e.originY=we(e.originY),l*=-1,e.signY=g)}const f=n.scaleX,p=n.scaleY;return o?("x"===o&&n.set(H,c),"y"===o&&n.set(N,l)):(!Te(n,"lockScalingX")&&n.set(H,c),!Te(n,"lockScalingY")&&n.set(N,l)),f!==n.scaleX||p!==n.scaleY}const pi=si(R,ii(((t,e,s,i)=>fi(t,e,s,i)))),mi=si(R,ii(((t,e,s,i)=>fi(t,e,s,i,{by:"x"})))),vi=si(R,ii(((t,e,s,i)=>fi(t,e,s,i,{by:"y"})))),yi=["target","ex","ey","skewingSide"],_i={x:{counterAxis:"y",scale:H,skew:U,lockSkewing:"lockSkewingX",origin:"originX",flip:"flipX"},y:{counterAxis:"x",scale:N,skew:q,lockSkewing:"lockSkewingY",origin:"originY",flip:"flipY"}},xi=["ns","nesw","ew","nwse"],Ci=(t,e,s)=>{if(0!==e.x&&Te(s,"lockSkewingY"))return be;if(0!==e.y&&Te(s,"lockSkewingX"))return be;const i=ke(s,e)%4;return"".concat(xi[i],"-resize")};function bi(t,e,r,n,o){const{target:a}=r,{counterAxis:h,origin:c,lockSkewing:l,skew:u,flip:d}=_i[t];if(Te(a,l))return!1;const{origin:g,flip:f}=_i[h],p=Ce(r[g])*(a[f]?-1:1),m=-Math.sign(p)*(a[d]?-1:1),v=.5*-((0===a[u]&&De(r,D,D,n,o)[t]>0||a[u]>0?1:-1)*m)+.5,y=si(X,ii(((e,s,r,n)=>function(t,e,s){let{target:r,ex:n,ey:o,skewingSide:a}=e,h=i(e,yi);const{skew:c}=_i[t],l=s.subtract(new ot(n,o)).divide(new ot(r.scaleX,r.scaleY))[t],u=r[c],d=h[c],g=Math.tan(xt(d)),f="y"===t?r._getTransformedDimensions({scaleX:1,scaleY:1,skewX:0}).x:r._getTransformedDimensions({scaleX:1,scaleY:1}).y,p=2*l*a/Math.max(f,1)+g,m=Ct(Math.atan(p));r.set(c,m);const v=u!==r[c];if(v&&"y"===t){const{skewX:t,scaleX:e}=r,s=r._getTransformedDimensions({skewY:u}),i=r._getTransformedDimensions(),n=0!==t?s.x/i.x:1;1!==n&&r.set(H,n*e)}return v}(t,s,new ot(r,n)))));return y(e,s(s({},r),{},{[c]:v,skewingSide:m}),n,o)}const Si=(t,e,s,i)=>bi("x",t,e,s,i),wi=(t,e,s,i)=>bi("y",t,e,s,i);function Ti(t,e){return t[e.canvas.altActionKey]}const Oi=(t,e,s)=>{const i=Ti(t,s);return 0===e.x?i?U:N:0===e.y?i?q:H:""},ki=(t,e,s)=>Ti(t,s)?Ci(0,e,s):gi(t,e,s),Di=(t,e,s,i)=>Ti(t,e.target)?wi(t,e,s,i):mi(t,e,s,i),Mi=(t,e,s,i)=>Ti(t,e.target)?Si(t,e,s,i):vi(t,e,s,i),Pi=()=>({ml:new ai({x:-.5,y:0,cursorStyleHandler:ki,actionHandler:Di,getActionName:Oi}),mr:new ai({x:.5,y:0,cursorStyleHandler:ki,actionHandler:Di,getActionName:Oi}),mb:new ai({x:0,y:.5,cursorStyleHandler:ki,actionHandler:Mi,getActionName:Oi}),mt:new ai({x:0,y:-.5,cursorStyleHandler:ki,actionHandler:Mi,getActionName:Oi}),tl:new ai({x:-.5,y:-.5,cursorStyleHandler:gi,actionHandler:pi}),tr:new ai({x:.5,y:-.5,cursorStyleHandler:gi,actionHandler:pi}),bl:new ai({x:-.5,y:.5,cursorStyleHandler:gi,actionHandler:pi}),br:new ai({x:.5,y:.5,cursorStyleHandler:gi,actionHandler:pi}),mtr:new ai({x:0,y:-.5,actionHandler:ci,cursorStyleHandler:hi,offsetY:-40,withConnection:!0,actionName:B})}),Ei=()=>({mr:new ai({x:.5,y:0,actionHandler:ri,cursorStyleHandler:ki,actionName:Y}),ml:new ai({x:-.5,y:0,actionHandler:ri,cursorStyleHandler:ki,actionName:Y})}),Ai=()=>s(s({},Pi()),Ei());class ji extends ei{static getDefaults(){return s(s({},super.getDefaults()),ji.ownDefaults)}constructor(t){super(),Object.assign(this,this.constructor.createControls(),ji.ownDefaults),this.setOptions(t)}static createControls(){return{controls:Pi()}}_updateCacheCanvas(){const t=this.canvas;if(this.noScaleCache&&t&&t._currentTransform){const e=t._currentTransform,s=e.target,i=e.action;if(this===s&&i&&i.startsWith(G))return!1}return super._updateCacheCanvas()}getActiveControl(){const t=this.__corner;return t?{key:t,control:this.controls[t],coord:this.oCoords[t]}:void 0}findControl(t){let e=arguments.length>1&&void 0!==arguments[1]&&arguments[1];if(!this.hasControls||!this.canvas)return;this.__corner=void 0;const s=Object.entries(this.oCoords);for(let i=s.length-1;i>=0;i--){const[r,n]=s[i],o=this.controls[r];if(o.shouldActivate(r,this,t,e?n.touchCorner:n.corner))return this.__corner=r,{key:r,control:o,coord:this.oCoords[r]}}}calcOCoords(){const t=this.getViewportTransform(),e=this.getCenterPoint(),s=Mt(e.x,e.y),i=Pt({angle:this.getTotalAngle()-(this.group&&this.flipX?180:0)}),r=Tt(s,i),n=Tt(t,r),o=Tt(n,[1/t[0],0,0,1/t[3],0,0]),a=this.group?Dt(this.calcTransformMatrix()):void 0;a&&(a.scaleX=Math.abs(a.scaleX),a.scaleY=Math.abs(a.scaleY));const h=this._calculateCurrentDimensions(a),c={};return this.forEachControl(((t,e)=>{const s=t.positionHandler(h,o,this,t);c[e]=Object.assign(s,this._calcCornerCoords(t,s))})),c}_calcCornerCoords(t,e){const s=this.getTotalAngle();return{corner:t.calcCornerCoords(s,this.cornerSize,e.x,e.y,!1,this),touchCorner:t.calcCornerCoords(s,this.touchCornerSize,e.x,e.y,!0,this)}}setCoords(){super.setCoords(),this.canvas&&(this.oCoords=this.calcOCoords())}forEachControl(t){for(const e in this.controls)t(this.controls[e],e,this)}drawSelectionBackground(t){if(!this.selectionBackgroundColor||this.canvas&&this.canvas._activeObject!==this)return;t.save();const e=this.getRelativeCenterPoint(),s=this._calculateCurrentDimensions(),i=this.getViewportTransform();t.translate(e.x,e.y),t.scale(1/i[0],1/i[3]),t.rotate(xt(this.angle)),t.fillStyle=this.selectionBackgroundColor,t.fillRect(-s.x/2,-s.y/2,s.x,s.y),t.restore()}strokeBorders(t,e){t.strokeRect(-e.x/2,-e.y/2,e.x,e.y)}_drawBorders(t,e){let i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{};const r=s({hasControls:this.hasControls,borderColor:this.borderColor,borderDashArray:this.borderDashArray},i);t.save(),t.strokeStyle=r.borderColor,this._setLineDash(t,r.borderDashArray),this.strokeBorders(t,e),r.hasControls&&this.drawControlsConnectingLines(t,e),t.restore()}_renderControls(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const{hasBorders:i,hasControls:r}=this,n=s({hasBorders:i,hasControls:r},e),o=this.getViewportTransform(),a=n.hasBorders,h=n.hasControls,c=Tt(o,this.calcTransformMatrix()),l=Dt(c);t.save(),t.translate(l.translateX,l.translateY),t.lineWidth=this.borderScaleFactor,this.group===this.parent&&(t.globalAlpha=this.isMoving?this.borderOpacityWhenMoving:1),this.flipX&&(l.angle-=180),t.rotate(xt(this.group?l.angle:this.angle)),a&&this.drawBorders(t,l,e),h&&this.drawControls(t,e),t.restore()}drawBorders(t,e,s){let i;if(s&&s.forActiveSelection||this.group){const t=fe(this.width,this.height,Lt(e)),s=this.isStrokeAccountedForInDimensions()?at:(this.strokeUniform?(new ot).scalarAdd(this.canvas?this.canvas.getZoom():1):new ot(e.scaleX,e.scaleY)).scalarMultiply(this.strokeWidth);i=t.add(s).scalarAdd(this.borderScaleFactor).scalarAdd(2*this.padding)}else i=this._calculateCurrentDimensions().scalarAdd(this.borderScaleFactor);this._drawBorders(t,i,s)}drawControlsConnectingLines(t,e){let s=!1;t.beginPath(),this.forEachControl(((i,r)=>{i.withConnection&&i.getVisibility(this,r)&&(s=!0,t.moveTo(i.x*e.x,i.y*e.y),t.lineTo(i.x*e.x+i.offsetX,i.y*e.y+i.offsetY))})),s&&t.stroke()}drawControls(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};t.save();const i=this.getCanvasRetinaScaling(),{cornerStrokeColor:r,cornerDashArray:n,cornerColor:o}=this,a=s({cornerStrokeColor:r,cornerDashArray:n,cornerColor:o},e);t.setTransform(i,0,0,i,0,0),t.strokeStyle=t.fillStyle=a.cornerColor,this.transparentCorners||(t.strokeStyle=a.cornerStrokeColor),this._setLineDash(t,a.cornerDashArray),this.forEachControl(((e,s)=>{if(e.getVisibility(this,s)){const i=this.oCoords[s];e.render(t,i.x,i.y,a,this)}})),t.restore()}isControlVisible(t){return this.controls[t]&&this.controls[t].getVisibility(this,t)}setControlVisible(t,e){this._controlsVisibility||(this._controlsVisibility={}),this._controlsVisibility[t]=e}setControlsVisibility(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};Object.entries(t).forEach((t=>{let[e,s]=t;return this.setControlVisible(e,s)}))}clearContextTop(t){if(!this.canvas)return;const e=this.canvas.contextTop;if(!e)return;const s=this.canvas.viewportTransform;e.save(),e.transform(s[0],s[1],s[2],s[3],s[4],s[5]),this.transform(e);const i=this.width+4,r=this.height+4;return e.clearRect(-i/2,-r/2,i,r),t||e.restore(),e}onDeselect(t){return!1}onSelect(t){return!1}shouldStartDragging(t){return!1}onDragStart(t){return!1}canDrop(t){return!1}renderDragSourceEffect(t){}renderDropTargetEffect(t){}}function Fi(t,e){return e.forEach((e=>{Object.getOwnPropertyNames(e.prototype).forEach((s=>{"constructor"!==s&&Object.defineProperty(t.prototype,s,Object.getOwnPropertyDescriptor(e.prototype,s)||Object.create(null))}))})),t}t(ji,"ownDefaults",{noScaleCache:!0,lockMovementX:!1,lockMovementY:!1,lockRotation:!1,lockScalingX:!1,lockScalingY:!1,lockSkewingX:!1,lockSkewingY:!1,lockScalingFlip:!1,cornerSize:13,touchCornerSize:24,transparentCorners:!0,cornerColor:"rgb(178,204,255)",cornerStrokeColor:"",cornerStyle:"rect",cornerDashArray:null,hasControls:!0,borderColor:"rgb(178,204,255)",borderDashArray:null,borderOpacityWhenMoving:.4,borderScaleFactor:1,hasBorders:!0,selectionBackgroundColor:"",selectable:!0,evented:!0,perPixelTargetFind:!1,activeOn:"down",hoverCursor:null,moveCursor:null});class Li extends ji{}Fi(Li,[We]),tt.setClass(Li),tt.setClass(Li,"object");const Ri=(t,e,s,i)=>{const r=2*(i=Math.round(i))+1,{data:n}=t.getImageData(e-i,s-i,r,r);for(let t=3;t<n.length;t+=4){if(n[t]>0)return!1}return!0};class Ii{constructor(t){this.options=t,this.strokeProjectionMagnitude=this.options.strokeWidth/2,this.scale=new ot(this.options.scaleX,this.options.scaleY),this.strokeUniformScalar=this.options.strokeUniform?new ot(1/this.options.scaleX,1/this.options.scaleY):new ot(1,1)}createSideVector(t,e){const s=vs(t,e);return this.options.strokeUniform?s.multiply(this.scale):s}projectOrthogonally(t,e,s){return this.applySkew(t.add(this.calcOrthogonalProjection(t,e,s)))}isSkewed(){return 0!==this.options.skewX||0!==this.options.skewY}applySkew(t){const e=new ot(t);return e.y+=e.x*Math.tan(xt(this.options.skewY)),e.x+=e.y*Math.tan(xt(this.options.skewX)),e}scaleUnitVector(t,e){return t.multiply(this.strokeUniformScalar).scalarMultiply(e)}}const Bi=new ot;class Xi extends Ii{static getOrthogonalRotationFactor(t,e){const s=e?_s(t,e):xs(t);return Math.abs(s)<b?-1:1}constructor(e,s,i,r){super(r),t(this,"AB",void 0),t(this,"AC",void 0),t(this,"alpha",void 0),t(this,"bisector",void 0),this.A=new ot(e),this.B=new ot(s),this.C=new ot(i),this.AB=this.createSideVector(this.A,this.B),this.AC=this.createSideVector(this.A,this.C),this.alpha=_s(this.AB,this.AC),this.bisector=Cs(ms(this.AB.eq(Bi)?this.AC:this.AB,this.alpha/2))}calcOrthogonalProjection(t,e){let s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:this.strokeProjectionMagnitude;const i=this.createSideVector(t,e),r=bs(i),n=Xi.getOrthogonalRotationFactor(r,this.bisector);return this.scaleUnitVector(r,s*n)}projectBevel(){const t=[];return(this.alpha%S==0?[this.B]:[this.B,this.C]).forEach((e=>{t.push(this.projectOrthogonally(this.A,e)),t.push(this.projectOrthogonally(this.A,e,-this.strokeProjectionMagnitude))})),t}projectMiter(){const t=[],e=Math.abs(this.alpha),s=1/Math.sin(e/2),i=this.scaleUnitVector(this.bisector,-this.strokeProjectionMagnitude*s),r=this.options.strokeUniform?ys(this.scaleUnitVector(this.bisector,this.options.strokeMiterLimit)):this.options.strokeMiterLimit;return ys(i)/this.strokeProjectionMagnitude<=r&&t.push(this.applySkew(this.A.add(i))),t.push(...this.projectBevel()),t}projectRoundNoSkew(t,e){const s=[],i=new ot(Xi.getOrthogonalRotationFactor(this.bisector),Xi.getOrthogonalRotationFactor(new ot(this.bisector.y,this.bisector.x)));return[new ot(1,0).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar).multiply(i),new ot(0,1).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar).multiply(i)].forEach((i=>{Ts(i,t,e)&&s.push(this.A.add(i))})),s}projectRoundWithSkew(t,e){const s=[],{skewX:i,skewY:r,scaleX:n,scaleY:o,strokeUniform:a}=this.options,h=new ot(Math.tan(xt(i)),Math.tan(xt(r))),c=this.strokeProjectionMagnitude,l=a?c/o/Math.sqrt(1/o**2+1/n**2*h.y**2):c/Math.sqrt(1+h.y**2),u=new ot(Math.sqrt(Math.max(c**2-l**2,0)),l),d=a?c/Math.sqrt(1+h.x**2*(1/o)**2/(1/n+1/n*h.x*h.y)**2):c/Math.sqrt(1+h.x**2/(1+h.x*h.y)**2),g=new ot(d,Math.sqrt(Math.max(c**2-d**2,0)));return[g,g.scalarMultiply(-1),u,u.scalarMultiply(-1)].map((t=>this.applySkew(a?t.multiply(this.strokeUniformScalar):t))).forEach((i=>{Ts(i,t,e)&&s.push(this.applySkew(this.A).add(i))})),s}projectRound(){const t=[];t.push(...this.projectBevel());const e=this.alpha%S==0,s=this.applySkew(this.A),i=t[e?0:2].subtract(s),r=t[e?1:0].subtract(s),n=e?this.applySkew(this.AB.scalarMultiply(-1)):this.applySkew(this.bisector.multiply(this.strokeUniformScalar).scalarMultiply(-1)),o=Ss(i,n)>0,a=o?i:r,h=o?r:i;return this.isSkewed()?t.push(...this.projectRoundWithSkew(a,h)):t.push(...this.projectRoundNoSkew(a,h)),t}projectPoints(){switch(this.options.strokeLineJoin){case"miter":return this.projectMiter();case"round":return this.projectRound();default:return this.projectBevel()}}project(){return this.projectPoints().map((t=>({originPoint:this.A,projectedPoint:t,angle:this.alpha,bisector:this.bisector})))}}class Yi extends Ii{constructor(t,e,s){super(s),this.A=new ot(t),this.T=new ot(e)}calcOrthogonalProjection(t,e){let s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:this.strokeProjectionMagnitude;const i=this.createSideVector(t,e);return this.scaleUnitVector(bs(i),s)}projectButt(){return[this.projectOrthogonally(this.A,this.T,this.strokeProjectionMagnitude),this.projectOrthogonally(this.A,this.T,-this.strokeProjectionMagnitude)]}projectRound(){const t=[];if(!this.isSkewed()&&this.A.eq(this.T)){const e=new ot(1,1).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar);t.push(this.applySkew(this.A.add(e)),this.applySkew(this.A.subtract(e)))}else t.push(...new Xi(this.A,this.T,this.T,this.options).projectRound());return t}projectSquare(){const t=[];if(this.A.eq(this.T)){const e=new ot(1,1).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar);t.push(this.A.add(e),this.A.subtract(e))}else{const e=this.calcOrthogonalProjection(this.A,this.T,this.strokeProjectionMagnitude),s=this.scaleUnitVector(Cs(this.createSideVector(this.A,this.T)),-this.strokeProjectionMagnitude),i=this.A.add(s);t.push(i.add(e),i.subtract(e))}return t.map((t=>this.applySkew(t)))}projectPoints(){switch(this.options.strokeLineCap){case"round":return this.projectRound();case"square":return this.projectSquare();default:return this.projectButt()}}project(){return this.projectPoints().map((t=>({originPoint:this.A,projectedPoint:t})))}}const Wi=function(t,e){let s=arguments.length>2&&void 0!==arguments[2]&&arguments[2];const i=[];if(0===t.length)return i;const r=t.reduce(((t,e)=>(t[t.length-1].eq(e)||t.push(new ot(e)),t)),[new ot(t[0])]);if(1===r.length)s=!0;else if(!s){const t=r[0],e=((t,e)=>{for(let s=t.length-1;s>=0;s--)if(e(t[s],s,t))return s;return-1})(r,(e=>!e.eq(t)));r.splice(e+1)}return r.forEach(((t,r,n)=>{let o,a;0===r?(a=n[1],o=s?t:n[n.length-1]):r===n.length-1?(o=n[r-1],a=s?t:n[0]):(o=n[r-1],a=n[r+1]),s&&1===n.length?i.push(...new Yi(t,t,e).project()):!s||0!==r&&r!==n.length-1?i.push(...new Xi(t,o,a,e).project()):i.push(...new Yi(t,0===r?a:o,e).project())})),i},Vi=t=>{const e={};return Object.keys(t).forEach((i=>{e[i]={},Object.keys(t[i]).forEach((r=>{e[i][r]=s({},t[i][r])}))})),e},zi=t=>t.replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/'/g,"&apos;").replace(/</g,"&lt;").replace(/>/g,"&gt;");let Gi;const Hi=t=>{if(Gi||Gi||(Gi="Intl"in v()&&"Segmenter"in Intl&&new Intl.Segmenter(void 0,{granularity:"grapheme"})),Gi){const e=Gi.segment(t);return Array.from(e).map((t=>{let{segment:e}=t;return e}))}return Ni(t)},Ni=t=>{const e=[];for(let s,i=0;i<t.length;i++)!1!==(s=Ui(t,i))&&e.push(s);return e},Ui=(t,e)=>{const s=t.charCodeAt(e);if(isNaN(s))return"";if(s<55296||s>57343)return t.charAt(e);if(55296<=s&&s<=56319){if(t.length<=e+1)throw"High surrogate without following low surrogate";const s=t.charCodeAt(e+1);if(56320>s||s>57343)throw"High surrogate without following low surrogate";return t.charAt(e)+t.charAt(e+1)}if(0===e)throw"Low surrogate without preceding high surrogate";const i=t.charCodeAt(e-1);if(55296>i||i>56319)throw"Low surrogate without preceding high surrogate";return!1};var qi=Object.freeze({__proto__:null,capitalize:function(t){let e=arguments.length>1&&void 0!==arguments[1]&&arguments[1];return"".concat(t.charAt(0).toUpperCase()).concat(e?t.slice(1):t.slice(1).toLowerCase())},escapeXml:zi,graphemeSplit:Hi});const Ki=function(t,e){let s=arguments.length>2&&void 0!==arguments[2]&&arguments[2];return t.fill!==e.fill||t.stroke!==e.stroke||t.strokeWidth!==e.strokeWidth||t.fontSize!==e.fontSize||t.fontFamily!==e.fontFamily||t.fontWeight!==e.fontWeight||t.fontStyle!==e.fontStyle||t.textDecorationThickness!==e.textDecorationThickness||t.textBackgroundColor!==e.textBackgroundColor||t.deltaY!==e.deltaY||s&&(t.overline!==e.overline||t.underline!==e.underline||t.linethrough!==e.linethrough)},Ji=(t,e)=>{const s=e.split("\n"),i=[];let r=-1,n={};t=Vi(t);for(let e=0;e<s.length;e++){const o=Hi(s[e]);if(t[e])for(let s=0;s<o.length;s++){r++;const o=t[e][s];o&&Object.keys(o).length>0&&(Ki(n,o,!0)?i.push({start:r,end:r+1,style:o}):i[i.length-1].end++),n=o||{}}else r+=o.length,n={}}return i},Qi=(t,e)=>{if(!Array.isArray(t))return Vi(t);const i=e.split(F),r={};let n=-1,o=0;for(let e=0;e<i.length;e++){const a=Hi(i[e]);for(let i=0;i<a.length;i++)n++,t[o]&&t[o].start<=n&&n<t[o].end&&(r[e]=r[e]||{},r[e][i]=s({},t[o].style),n===t[o].end-1&&o++)}return r},Zi=["display","transform",K,"fill-opacity","fill-rule","opacity",J,"stroke-dasharray","stroke-linecap","stroke-dashoffset","stroke-linejoin","stroke-miterlimit","stroke-opacity","stroke-width","id","paint-order","vector-effect","instantiated_by_use","clip-path"];function $i(t,e){const s=t.nodeName,i=t.getAttribute("class"),r=t.getAttribute("id"),n="(?![a-zA-Z\\-]+)";let o;if(o=new RegExp("^"+s,"i"),e=e.replace(o,""),r&&e.length&&(o=new RegExp("#"+r+n,"i"),e=e.replace(o,"")),i&&e.length){const t=i.split(" ");for(let s=t.length;s--;)o=new RegExp("\\."+t[s]+n,"i"),e=e.replace(o,"")}return 0===e.length}function tr(t,e){let s=!0;const i=$i(t,e.pop());return i&&e.length&&(s=function(t,e){let s,i=!0;for(;t.parentElement&&1===t.parentElement.nodeType&&e.length;)i&&(s=e.pop()),i=$i(t=t.parentElement,s);return 0===e.length}(t,e)),i&&s&&0===e.length}const er=t=>{var e;return null!==(e=as[t])&&void 0!==e?e:t},sr=new RegExp("(".concat(is,")"),"gi"),ir=t=>Pe(t.replace(sr," $1 ").replace(/,/gi," "));var rr,nr,or,ar,hr,cr,lr;const ur="(".concat(is,")"),dr=String.raw(rr||(rr=r(["(skewX)(",")"],["(skewX)\\(","\\)"])),ur),gr=String.raw(nr||(nr=r(["(skewY)(",")"],["(skewY)\\(","\\)"])),ur),fr=String.raw(or||(or=r(["(rotate)(","(?: "," ",")?)"],["(rotate)\\(","(?: "," ",")?\\)"])),ur,ur,ur),pr=String.raw(ar||(ar=r(["(scale)(","(?: ",")?)"],["(scale)\\(","(?: ",")?\\)"])),ur,ur),mr=String.raw(hr||(hr=r(["(translate)(","(?: ",")?)"],["(translate)\\(","(?: ",")?\\)"])),ur,ur),vr=String.raw(cr||(cr=r(["(matrix)("," "," "," "," "," ",")"],["(matrix)\\("," "," "," "," "," ","\\)"])),ur,ur,ur,ur,ur,ur),yr="(?:".concat(vr,"|").concat(mr,"|").concat(fr,"|").concat(pr,"|").concat(dr,"|").concat(gr,")"),_r="(?:".concat(yr,"*)"),xr=String.raw(lr||(lr=r(["^s*(?:","?)s*$"],["^\\s*(?:","?)\\s*$"])),_r),Cr=new RegExp(xr),br=new RegExp(yr),Sr=new RegExp(yr,"g");function wr(t){const e=[];if(!(t=ir(t).replace(/\s*([()])\s*/gi,"$1"))||t&&!Cr.test(t))return[...T];for(const s of t.matchAll(Sr)){const t=br.exec(s[0]);if(!t)continue;let i=T;const r=t.filter((t=>!!t)),[,n,...o]=r,[a,h,c,l,u,d]=o.map((t=>parseFloat(t)));switch(n){case"translate":i=Mt(a,h);break;case B:i=Pt({angle:a},{x:h,y:c});break;case G:i=Et(a,h);break;case U:i=jt(a);break;case q:i=Ft(a);break;case"matrix":i=[a,h,c,l,u,d]}e.push(i)}return Ot(e)}function Tr(t,e,s,i){const r=Array.isArray(e);let n,o=e;if(t!==K&&t!==J||e!==j){if("strokeUniform"===t)return"non-scaling-stroke"===e;if("strokeDashArray"===t)o=e===j?null:e.replace(/,/g," ").split(/\s+/).map(parseFloat);else if("transformMatrix"===t)o=s&&s.transformMatrix?Tt(s.transformMatrix,wr(e)):wr(e);else if("visible"===t)o=e!==j&&"hidden"!==e,s&&!1===s.visible&&(o=!1);else if("opacity"===t)o=parseFloat(e),s&&void 0!==s.opacity&&(o*=s.opacity);else if("textAnchor"===t)o="start"===e?M:"end"===e?A:D;else if("charSpacing"===t||t===ze)n=Be(e,i)/i*1e3;else if("paintFirst"===t){const t=e.indexOf(K),s=e.indexOf(J);o=K,(t>-1&&s>-1&&s<t||-1===t&&s>-1)&&(o=J)}else{if("href"===t||"xlink:href"===t||"font"===t||"id"===t)return e;if("imageSmoothing"===t)return"optimizeQuality"===e;n=r?e.map(Be):Be(e,i)}}else o="";return!r&&isNaN(n)?o:n}function Or(t,e){const s=t.match(os);if(!s)return;const i=s[1],r=s[3],n=s[4],o=s[5],a=s[6];i&&(e.fontStyle=i),r&&(e.fontWeight=isNaN(parseFloat(r))?r:parseFloat(r)),n&&(e.fontSize=Be(n)),a&&(e.fontFamily=a),o&&(e.lineHeight="normal"===o?1:o)}function kr(t,e){t.replace(/;\s*$/,"").split(";").forEach((t=>{if(!t)return;const[s,i]=t.split(":");e[s.trim().toLowerCase()]=i.trim()}))}function Dr(t){const e={},s=t.getAttribute("style");return s?("string"==typeof s?kr(s,e):function(t,e){Object.entries(t).forEach((t=>{let[s,i]=t;void 0!==i&&(e[s.toLowerCase()]=i)}))}(s,e),e):e}const Mr={stroke:"strokeOpacity",fill:"fillOpacity"};function Pr(t,e,i){if(!t)return{};let r,n={},o=O;t.parentNode&&ds.test(t.parentNode.nodeName)&&(n=Pr(t.parentElement,e,i),n.fontSize&&(r=o=Be(n.fontSize)));const a=s(s(s({},e.reduce(((e,s)=>{const i=t.getAttribute(s);return i&&(e[s]=i),e}),{})),function(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},i={};for(const r in e)tr(t,r.split(" "))&&(i=s(s({},i),e[r]));return i}(t,i)),Dr(t));a[cs]&&t.setAttribute(cs,a[cs]),a[hs]&&(r=Be(a[hs],o),a[hs]="".concat(r));const h={};for(const t in a){const e=er(t),s=Tr(e,a[t],n,r);h[e]=s}h&&h.font&&Or(h.font,h);const c=s(s({},n),h);return ds.test(t.nodeName)?c:function(t){const e=Li.getDefaults();return Object.entries(Mr).forEach((s=>{let[i,r]=s;if(void 0===t[r]||""===t[i])return;if(void 0===t[i]){if(!e[i])return;t[i]=e[i]}if(0===t[i].indexOf("url("))return;const n=new Ie(t[i]);t[i]=n.setAlpha(Vt(n.getAlpha()*t[r],2)).toRgba()})),t}(c)}const Er=["left","top","width","height","visible"],Ar=["rx","ry"];class jr extends Li{static getDefaults(){return s(s({},super.getDefaults()),jr.ownDefaults)}constructor(t){super(),Object.assign(this,jr.ownDefaults),this.setOptions(t),this._initRxRy()}_initRxRy(){const{rx:t,ry:e}=this;t&&!e?this.ry=t:e&&!t&&(this.rx=e)}_render(t){const{width:e,height:s}=this,i=-e/2,r=-s/2,n=this.rx?Math.min(this.rx,e/2):0,o=this.ry?Math.min(this.ry,s/2):0,a=0!==n||0!==o;t.beginPath(),t.moveTo(i+n,r),t.lineTo(i+e-n,r),a&&t.bezierCurveTo(i+e-k*n,r,i+e,r+k*o,i+e,r+o),t.lineTo(i+e,r+s-o),a&&t.bezierCurveTo(i+e,r+s-k*o,i+e-k*n,r+s,i+e-n,r+s),t.lineTo(i+n,r+s),a&&t.bezierCurveTo(i+k*n,r+s,i,r+s-k*o,i,r+s-o),t.lineTo(i,r+o),a&&t.bezierCurveTo(i,r+k*o,i+k*n,r,i+n,r),t.closePath(),this._renderPaintInOrder(t)}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return super.toObject([...Ar,...t])}_toSVG(){const{width:t,height:e,rx:s,ry:i}=this;return["<rect ","COMMON_PARTS",'x="'.concat(-t/2,'" y="').concat(-e/2,'" rx="').concat(s,'" ry="').concat(i,'" width="').concat(t,'" height="').concat(e,'" />\n')]}static async fromElement(t,e,r){const n=Pr(t,this.ATTRIBUTE_NAMES,r),{left:o=0,top:a=0,width:h=0,height:c=0,visible:l=!0}=n,u=i(n,Er);return new this(s(s(s({},e),u),{},{left:o,top:a,width:h,height:c,visible:Boolean(l&&h&&c)}))}}t(jr,"type","Rect"),t(jr,"cacheProperties",[...Es,...Ar]),t(jr,"ownDefaults",{rx:0,ry:0}),t(jr,"ATTRIBUTE_NAMES",[...Zi,"x","y","rx","ry","width","height"]),tt.setClass(jr),tt.setSVGClass(jr);const Fr="initialization",Lr="added",Rr="removed",Ir="imperative",Br=(t,e)=>{const{strokeUniform:s,strokeWidth:i,width:r,height:n,group:o}=e,a=o&&o!==t?pe(o.calcTransformMatrix(),t.calcTransformMatrix()):null,h=a?e.getRelativeCenterPoint().transform(a):e.getRelativeCenterPoint(),c=!e.isStrokeAccountedForInDimensions(),l=s&&c?ve(new ot(i,i),void 0,t.calcTransformMatrix()):at,u=!s&&c?i:0,d=fe(r+u,n+u,Ot([a,e.calcOwnMatrix()],!0)).add(l).scalarDivide(2);return[h.subtract(d),h.add(d)]};class Xr{calcLayoutResult(t,e){if(this.shouldPerformLayout(t))return this.calcBoundingBox(e,t)}shouldPerformLayout(t){let{type:e,prevStrategy:s,strategy:i}=t;return e===Fr||e===Ir||!!s&&i!==s}shouldLayoutClipPath(t){let{type:e,target:{clipPath:s}}=t;return e!==Fr&&s&&!s.absolutePositioned}getInitialSize(t,e){return e.size}calcBoundingBox(t,e){const{type:s,target:i}=e;if(s===Ir&&e.overrides)return e.overrides;if(0===t.length)return;const{left:r,top:n,width:o,height:a}=he(t.map((t=>Br(i,t))).reduce(((t,e)=>t.concat(e)),[])),h=new ot(o,a),c=new ot(r,n).add(h.scalarDivide(2));if(s===Fr){const t=this.getInitialSize(e,{size:h,center:c});return{center:c,relativeCorrection:new ot(0,0),size:t}}return{center:c.transform(i.calcOwnMatrix()),size:h}}}t(Xr,"type","strategy");class Yr extends Xr{shouldPerformLayout(t){return!0}}t(Yr,"type","fit-content"),tt.setClass(Yr);const Wr=["strategy"],Vr=["target","strategy","bubbles","prevStrategy"],zr="layoutManager";class Gr{constructor(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:new Yr;t(this,"strategy",void 0),this.strategy=e,this._subscriptions=new Map}performLayout(t){const e=s(s({bubbles:!0,strategy:this.strategy},t),{},{prevStrategy:this._prevLayoutStrategy,stopPropagation(){this.bubbles=!1}});this.onBeforeLayout(e);const i=this.getLayoutResult(e);i&&this.commitLayout(e,i),this.onAfterLayout(e,i),this._prevLayoutStrategy=e.strategy}attachHandlers(t,e){const{target:s}=e;return[Q,L,Y,I,R,X,z,W,V].map((e=>t.on(e,(t=>this.performLayout(e===Q?{type:"object_modified",trigger:e,e:t,target:s}:{type:"object_modifying",trigger:e,e:t,target:s})))))}subscribe(t,e){this.unsubscribe(t,e);const s=this.attachHandlers(t,e);this._subscriptions.set(t,s)}unsubscribe(t,e){(this._subscriptions.get(t)||[]).forEach((t=>t())),this._subscriptions.delete(t)}unsubscribeTargets(t){t.targets.forEach((e=>this.unsubscribe(e,t)))}subscribeTargets(t){t.targets.forEach((e=>this.subscribe(e,t)))}onBeforeLayout(t){const{target:e,type:r}=t,{canvas:n}=e;if(r===Fr||r===Lr?this.subscribeTargets(t):r===Rr&&this.unsubscribeTargets(t),e.fire("layout:before",{context:t}),n&&n.fire("object:layout:before",{target:e,context:t}),r===Ir&&t.deep){const r=i(t,Wr);e.forEachObject((t=>t.layoutManager&&t.layoutManager.performLayout(s(s({},r),{},{bubbles:!1,target:t}))))}}getLayoutResult(t){const{target:e,strategy:s,type:i}=t,r=s.calcLayoutResult(t,e.getObjects());if(!r)return;const n=i===Fr?new ot:e.getRelativeCenterPoint(),{center:o,correction:a=new ot,relativeCorrection:h=new ot}=r,c=n.subtract(o).add(a).transform(i===Fr?T:wt(e.calcOwnMatrix()),!0).add(h);return{result:r,prevCenter:n,nextCenter:o,offset:c}}commitLayout(t,e){const{target:s}=t,{result:{size:i},nextCenter:r}=e;var n,o;(s.set({width:i.x,height:i.y}),this.layoutObjects(t,e),t.type===Fr)?s.set({left:null!==(n=t.x)&&void 0!==n?n:r.x+i.x*Ce(s.originX),top:null!==(o=t.y)&&void 0!==o?o:r.y+i.y*Ce(s.originY)}):(s.setPositionByOrigin(r,D,D),s.setCoords(),s.set("dirty",!0))}layoutObjects(t,e){const{target:s}=t;s.forEachObject((i=>{i.group===s&&this.layoutObject(t,e,i)})),t.strategy.shouldLayoutClipPath(t)&&this.layoutObject(t,e,s.clipPath)}layoutObject(t,e,s){let{offset:i}=e;s.set({left:s.left+i.x,top:s.top+i.y})}onAfterLayout(t,e){const{target:r,strategy:n,bubbles:o,prevStrategy:a}=t,h=i(t,Vr),{canvas:c}=r;r.fire("layout:after",{context:t,result:e}),c&&c.fire("object:layout:after",{context:t,result:e,target:r});const l=r.parent;o&&null!=l&&l.layoutManager&&((h.path||(h.path=[])).push(r),l.layoutManager.performLayout(s(s({},h),{},{target:l}))),r.set("dirty",!0)}dispose(){const{_subscriptions:t}=this;t.forEach((t=>t.forEach((t=>t())))),t.clear()}toObject(){return{type:zr,strategy:this.strategy.constructor.type}}toJSON(){return this.toObject()}}tt.setClass(Gr,zr);const Hr=["type","objects","layoutManager"];class Nr extends Gr{performLayout(){}}class Ur extends(ct(Li)){static getDefaults(){return s(s({},super.getDefaults()),Ur.ownDefaults)}constructor(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[],s=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};super(),t(this,"_activeObjects",[]),t(this,"__objectSelectionTracker",void 0),t(this,"__objectSelectionDisposer",void 0),Object.assign(this,Ur.ownDefaults),this.setOptions(s),this.groupInit(e,s)}groupInit(t,e){var s;this._objects=[...t],this.__objectSelectionTracker=this.__objectSelectionMonitor.bind(this,!0),this.__objectSelectionDisposer=this.__objectSelectionMonitor.bind(this,!1),this.forEachObject((t=>{this.enterGroup(t,!1)})),this.layoutManager=null!==(s=e.layoutManager)&&void 0!==s?s:new Gr,this.layoutManager.performLayout({type:Fr,target:this,targets:[...t],x:e.left,y:e.top})}canEnterGroup(t){return t===this||this.isDescendantOf(t)?(a("error","Group: circular object trees are not supported, this call has no effect"),!1):-1===this._objects.indexOf(t)||(a("error","Group: duplicate objects are not supported inside group, this call has no effect"),!1)}_filterObjectsBeforeEnteringGroup(t){return t.filter(((t,e,s)=>this.canEnterGroup(t)&&s.indexOf(t)===e))}add(){for(var t=arguments.length,e=new Array(t),s=0;s<t;s++)e[s]=arguments[s];const i=this._filterObjectsBeforeEnteringGroup(e),r=super.add(...i);return this._onAfterObjectsChange(Lr,i),r}insertAt(t){for(var e=arguments.length,s=new Array(e>1?e-1:0),i=1;i<e;i++)s[i-1]=arguments[i];const r=this._filterObjectsBeforeEnteringGroup(s),n=super.insertAt(t,...r);return this._onAfterObjectsChange(Lr,r),n}remove(){const t=super.remove(...arguments);return this._onAfterObjectsChange(Rr,t),t}_onObjectAdded(t){this.enterGroup(t,!0),this.fire("object:added",{target:t}),t.fire("added",{target:this})}_onObjectRemoved(t,e){this.exitGroup(t,e),this.fire("object:removed",{target:t}),t.fire("removed",{target:this})}_onAfterObjectsChange(t,e){this.layoutManager.performLayout({type:t,targets:e,target:this})}_onStackOrderChanged(){this._set("dirty",!0)}_set(t,e){const s=this[t];return super._set(t,e),"canvas"===t&&s!==e&&(this._objects||[]).forEach((s=>{s._set(t,e)})),this}_shouldSetNestedCoords(){return this.subTargetCheck}removeAll(){return this._activeObjects=[],this.remove(...this._objects)}__objectSelectionMonitor(t,e){let{target:s}=e;const i=this._activeObjects;if(t)i.push(s),this._set("dirty",!0);else if(i.length>0){const t=i.indexOf(s);t>-1&&(i.splice(t,1),this._set("dirty",!0))}}_watchObject(t,e){t&&this._watchObject(!1,e),t?(e.on("selected",this.__objectSelectionTracker),e.on("deselected",this.__objectSelectionDisposer)):(e.off("selected",this.__objectSelectionTracker),e.off("deselected",this.__objectSelectionDisposer))}enterGroup(t,e){t.group&&t.group.remove(t),t._set("parent",this),this._enterGroup(t,e)}_enterGroup(t,e){e&&ue(t,Tt(wt(this.calcTransformMatrix()),t.calcTransformMatrix())),this._shouldSetNestedCoords()&&t.setCoords(),t._set("group",this),t._set("canvas",this.canvas),this._watchObject(!0,t);const s=this.canvas&&this.canvas.getActiveObject&&this.canvas.getActiveObject();s&&(s===t||t.isDescendantOf(s))&&this._activeObjects.push(t)}exitGroup(t,e){this._exitGroup(t,e),t._set("parent",void 0),t._set("canvas",void 0)}_exitGroup(t,e){t._set("group",void 0),e||(ue(t,Tt(this.calcTransformMatrix(),t.calcTransformMatrix())),t.setCoords()),this._watchObject(!1,t);const s=this._activeObjects.length>0?this._activeObjects.indexOf(t):-1;s>-1&&this._activeObjects.splice(s,1)}shouldCache(){const t=Li.prototype.shouldCache.call(this);if(t)for(let t=0;t<this._objects.length;t++)if(this._objects[t].willDrawShadow())return this.ownCaching=!1,!1;return t}willDrawShadow(){if(super.willDrawShadow())return!0;for(let t=0;t<this._objects.length;t++)if(this._objects[t].willDrawShadow())return!0;return!1}isOnACache(){return this.ownCaching||!!this.parent&&this.parent.isOnACache()}drawObject(t,e,s){this._renderBackground(t);for(let e=0;e<this._objects.length;e++){var i;const s=this._objects[e];null!==(i=this.canvas)&&void 0!==i&&i.preserveObjectStacking&&s.group!==this?(t.save(),t.transform(...wt(this.calcTransformMatrix())),s.render(t),t.restore()):s.group===this&&s.render(t)}this._drawClipPath(t,this.clipPath,s)}setCoords(){super.setCoords(),this._shouldSetNestedCoords()&&this.forEachObject((t=>t.setCoords()))}triggerLayout(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};this.layoutManager.performLayout(s({target:this,type:Ir},t))}render(t){this._transformDone=!0,super.render(t),this._transformDone=!1}__serializeObjects(t,e){const s=this.includeDefaultValues;return this._objects.filter((function(t){return!t.excludeFromExport})).map((function(i){const r=i.includeDefaultValues;i.includeDefaultValues=s;const n=i[t||"toObject"](e);return i.includeDefaultValues=r,n}))}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];const e=this.layoutManager.toObject();return s(s(s({},super.toObject(["subTargetCheck","interactive",...t])),"fit-content"!==e.strategy||this.includeDefaultValues?{layoutManager:e}:{}),{},{objects:this.__serializeObjects("toObject",t)})}toString(){return"#<Group: (".concat(this.complexity(),")>")}dispose(){this.layoutManager.unsubscribeTargets({targets:this.getObjects(),target:this}),this._activeObjects=[],this.forEachObject((t=>{this._watchObject(!1,t),t.dispose()})),super.dispose()}_createSVGBgRect(t){if(!this.backgroundColor)return"";const e=jr.prototype._toSVG.call(this),s=e.indexOf("COMMON_PARTS");e[s]='for="group" ';const i=e.join("");return t?t(i):i}_toSVG(t){const e=["<g ","COMMON_PARTS"," >\n"],s=this._createSVGBgRect(t);s&&e.push("\t\t",s);for(let s=0;s<this._objects.length;s++)e.push("\t\t",this._objects[s].toSVG(t));return e.push("</g>\n"),e}getSvgStyles(){const t=void 0!==this.opacity&&1!==this.opacity?"opacity: ".concat(this.opacity,";"):"",e=this.visible?"":" visibility: hidden;";return[t,this.getSvgFilter(),e].join("")}toClipPathSVG(t){const e=[],s=this._createSVGBgRect(t);s&&e.push("\t",s);for(let s=0;s<this._objects.length;s++)e.push("\t",this._objects[s].toClipPathSVG(t));return this._createBaseClipPathSVGMarkup(e,{reviver:t})}static fromObject(t,e){let{type:r,objects:n=[],layoutManager:o}=t,a=i(t,Hr);return Promise.all([Bt(n,e),Xt(a,e)]).then((t=>{let[e,i]=t;const r=new this(e,s(s(s({},a),i),{},{layoutManager:new Nr}));if(o){const t=tt.getClass(o.type),e=tt.getClass(o.strategy);r.layoutManager=new t(new e)}else r.layoutManager=new Gr;return r.layoutManager.subscribeTargets({type:Fr,target:r,targets:r.getObjects()}),r.setCoords(),r}))}}t(Ur,"type","Group"),t(Ur,"ownDefaults",{strokeWidth:0,subTargetCheck:!1,interactive:!1}),tt.setClass(Ur);const qr=(t,e)=>Math.min(e.width/t.width,e.height/t.height),Kr=(t,e)=>Math.max(e.width/t.width,e.height/t.height),Jr="\\s*,?\\s*",Qr="".concat(Jr,"(").concat(is,")"),Zr="".concat(Qr).concat(Qr).concat(Qr).concat(Jr,"([01])").concat(Jr,"([01])").concat(Qr).concat(Qr),$r={m:"l",M:"L"},tn=(t,e,s,i,r,n,o,a,h,c,l)=>{const u=rt(t),d=nt(t),g=rt(e),f=nt(e),p=s*r*g-i*n*f+o,m=i*r*g+s*n*f+a;return["C",c+h*(-s*r*d-i*n*u),l+h*(-i*r*d+s*n*u),p+h*(s*r*f+i*n*g),m+h*(i*r*f-s*n*g),p,m]},en=(t,e,s,i)=>{const r=Math.atan2(e,t),n=Math.atan2(i,s);return n>=r?n-r:2*Math.PI-(r-n)};function sn(t,e,s,i,r,n,a,h){let c;if(o.cachesBoundsOfCurve&&(c=[...arguments].join(),_.boundsOfCurveCache[c]))return _.boundsOfCurveCache[c];const l=Math.sqrt,u=Math.abs,d=[],g=[[0,0],[0,0]];let f=6*t-12*s+6*r,p=-3*t+9*s-9*r+3*a,m=3*s-3*t;for(let t=0;t<2;++t){if(t>0&&(f=6*e-12*i+6*n,p=-3*e+9*i-9*n+3*h,m=3*i-3*e),u(p)<1e-12){if(u(f)<1e-12)continue;const t=-m/f;0<t&&t<1&&d.push(t);continue}const s=f*f-4*m*p;if(s<0)continue;const r=l(s),o=(-f+r)/(2*p);0<o&&o<1&&d.push(o);const a=(-f-r)/(2*p);0<a&&a<1&&d.push(a)}let v=d.length;const y=v,x=an(t,e,s,i,r,n,a,h);for(;v--;){const{x:t,y:e}=x(d[v]);g[0][v]=t,g[1][v]=e}g[0][y]=t,g[1][y]=e,g[0][y+1]=a,g[1][y+1]=h;const C=[new ot(Math.min(...g[0]),Math.min(...g[1])),new ot(Math.max(...g[0]),Math.max(...g[1]))];return o.cachesBoundsOfCurve&&(_.boundsOfCurveCache[c]=C),C}const rn=(t,e,s)=>{let[i,r,n,o,a,h,c,l]=s;const u=((t,e,s,i,r,n,o)=>{if(0===s||0===i)return[];let a=0,h=0,c=0;const l=Math.PI,u=o*w,d=nt(u),g=rt(u),f=.5*(-g*t-d*e),p=.5*(-g*e+d*t),m=s**2,v=i**2,y=p**2,_=f**2,x=m*v-m*y-v*_;let C=Math.abs(s),b=Math.abs(i);if(x<0){const t=Math.sqrt(1-x/(m*v));C*=t,b*=t}else c=(r===n?-1:1)*Math.sqrt(x/(m*y+v*_));const S=c*C*p/b,T=-c*b*f/C,O=g*S-d*T+.5*t,k=d*S+g*T+.5*e;let D=en(1,0,(f-S)/C,(p-T)/b),M=en((f-S)/C,(p-T)/b,(-f-S)/C,(-p-T)/b);0===n&&M>0?M-=2*l:1===n&&M<0&&(M+=2*l);const P=Math.ceil(Math.abs(M/l*2)),E=[],A=M/P,j=8/3*Math.sin(A/4)*Math.sin(A/4)/Math.sin(A/2);let F=D+A;for(let t=0;t<P;t++)E[t]=tn(D,F,g,d,C,b,O,k,j,a,h),a=E[t][5],h=E[t][6],D=F,F+=A;return E})(c-t,l-e,r,n,a,h,o);for(let s=0,i=u.length;s<i;s++)u[s][1]+=t,u[s][2]+=e,u[s][3]+=t,u[s][4]+=e,u[s][5]+=t,u[s][6]+=e;return u},nn=t=>{let e=0,s=0,i=0,r=0;const n=[];let o,a=0,h=0;for(const c of t){const t=[...c];let l;switch(t[0]){case"l":t[1]+=e,t[2]+=s;case"L":e=t[1],s=t[2],l=["L",e,s];break;case"h":t[1]+=e;case"H":e=t[1],l=["L",e,s];break;case"v":t[1]+=s;case"V":s=t[1],l=["L",e,s];break;case"m":t[1]+=e,t[2]+=s;case"M":e=t[1],s=t[2],i=t[1],r=t[2],l=["M",e,s];break;case"c":t[1]+=e,t[2]+=s,t[3]+=e,t[4]+=s,t[5]+=e,t[6]+=s;case"C":a=t[3],h=t[4],e=t[5],s=t[6],l=["C",t[1],t[2],a,h,e,s];break;case"s":t[1]+=e,t[2]+=s,t[3]+=e,t[4]+=s;case"S":"C"===o?(a=2*e-a,h=2*s-h):(a=e,h=s),e=t[3],s=t[4],l=["C",a,h,t[1],t[2],e,s],a=l[3],h=l[4];break;case"q":t[1]+=e,t[2]+=s,t[3]+=e,t[4]+=s;case"Q":a=t[1],h=t[2],e=t[3],s=t[4],l=["Q",a,h,e,s];break;case"t":t[1]+=e,t[2]+=s;case"T":"Q"===o?(a=2*e-a,h=2*s-h):(a=e,h=s),e=t[1],s=t[2],l=["Q",a,h,e,s];break;case"a":t[6]+=e,t[7]+=s;case"A":rn(e,s,t).forEach((t=>n.push(t))),e=t[6],s=t[7];break;case"z":case"Z":e=i,s=r,l=["Z"]}l?(n.push(l),o=l[0]):o=""}return n},on=(t,e,s,i)=>Math.sqrt((s-t)**2+(i-e)**2),an=(t,e,s,i,r,n,o,a)=>h=>{const c=h**3,l=(t=>3*t**2*(1-t))(h),u=(t=>3*t*(1-t)**2)(h),d=(t=>(1-t)**3)(h);return new ot(o*c+r*l+s*u+t*d,a*c+n*l+i*u+e*d)},hn=t=>t**2,cn=t=>2*t*(1-t),ln=t=>(1-t)**2,un=(t,e,s,i,r,n,o,a)=>h=>{const c=hn(h),l=cn(h),u=ln(h),d=3*(u*(s-t)+l*(r-s)+c*(o-r)),g=3*(u*(i-e)+l*(n-i)+c*(a-n));return Math.atan2(g,d)},dn=(t,e,s,i,r,n)=>o=>{const a=hn(o),h=cn(o),c=ln(o);return new ot(r*a+s*h+t*c,n*a+i*h+e*c)},gn=(t,e,s,i,r,n)=>o=>{const a=1-o,h=2*(a*(s-t)+o*(r-s)),c=2*(a*(i-e)+o*(n-i));return Math.atan2(c,h)},fn=(t,e,s)=>{let i=new ot(e,s),r=0;for(let e=1;e<=100;e+=1){const s=t(e/100);r+=on(i.x,i.y,s.x,s.y),i=s}return r},pn=(t,e)=>{let i,r=0,n=0,o={x:t.x,y:t.y},a=s({},o),h=.01,c=0;const l=t.iterator,u=t.angleFinder;for(;n<e&&h>1e-4;)a=l(r),c=r,i=on(o.x,o.y,a.x,a.y),i+n>e?(r-=h,h/=2):(o=a,r+=h,n+=i);return s(s({},a),{},{angle:u(c)})},mn=t=>{let e,s,i=0,r=0,n=0,o=0,a=0;const h=[];for(const c of t){const t={x:r,y:n,command:c[0],length:0};switch(c[0]){case"M":s=t,s.x=o=r=c[1],s.y=a=n=c[2];break;case"L":s=t,s.length=on(r,n,c[1],c[2]),r=c[1],n=c[2];break;case"C":e=an(r,n,c[1],c[2],c[3],c[4],c[5],c[6]),s=t,s.iterator=e,s.angleFinder=un(r,n,c[1],c[2],c[3],c[4],c[5],c[6]),s.length=fn(e,r,n),r=c[5],n=c[6];break;case"Q":e=dn(r,n,c[1],c[2],c[3],c[4]),s=t,s.iterator=e,s.angleFinder=gn(r,n,c[1],c[2],c[3],c[4]),s.length=fn(e,r,n),r=c[3],n=c[4];break;case"Z":s=t,s.destX=o,s.destY=a,s.length=on(r,n,o,a),r=o,n=a}i+=s.length,h.push(s)}return h.push({length:i,x:r,y:n}),h},vn=function(t,e){let i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:mn(t),r=0;for(;e-i[r].length>0&&r<i.length-2;)e-=i[r].length,r++;const n=i[r],o=e/n.length,a=t[r];switch(n.command){case"M":return{x:n.x,y:n.y,angle:0};case"Z":return s(s({},new ot(n.x,n.y).lerp(new ot(n.destX,n.destY),o)),{},{angle:Math.atan2(n.destY-n.y,n.destX-n.x)});case"L":return s(s({},new ot(n.x,n.y).lerp(new ot(a[1],a[2]),o)),{},{angle:Math.atan2(a[2]-n.y,a[1]-n.x)});case"C":case"Q":return pn(n,e)}},yn=new RegExp("[mzlhvcsqta][^mzlhvcsqta]*","gi"),_n=new RegExp(Zr,"g"),xn=new RegExp(is,"gi"),Cn={m:2,l:2,h:1,v:1,c:6,s:4,q:4,t:2,a:7},bn=t=>{var e;const s=[],i=null!==(e=t.match(yn))&&void 0!==e?e:[];for(const t of i){const e=t[0];if("z"===e||"Z"===e){s.push([e]);continue}const i=Cn[e.toLowerCase()];let r=[];if("a"===e||"A"===e){_n.lastIndex=0;for(let e=null;e=_n.exec(t);)r.push(...e.slice(1))}else r=t.match(xn)||[];for(let t=0;t<r.length;t+=i){const n=new Array(i),o=$r[e];n[0]=t>0&&o?o:e;for(let e=0;e<i;e++)n[e+1]=parseFloat(r[t+e]);s.push(n)}}return s},Sn=function(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0,s=new ot(t[0]),i=new ot(t[1]),r=1,n=0;const o=[],a=t.length,h=a>2;let c;for(h&&(r=t[2].x<i.x?-1:t[2].x===i.x?0:1,n=t[2].y<i.y?-1:t[2].y===i.y?0:1),o.push(["M",s.x-r*e,s.y-n*e]),c=1;c<a;c++){if(!s.eq(i)){const t=s.midPointFrom(i);o.push(["Q",s.x,s.y,t.x,t.y])}s=t[c],c+1<t.length&&(i=t[c+1])}return h&&(r=s.x>t[c-2].x?1:s.x===t[c-2].x?0:-1,n=s.y>t[c-2].y?1:s.y===t[c-2].y?0:-1),o.push(["L",s.x+r*e,s.y+n*e]),o},wn=(t,e)=>t.map((t=>t.map(((t,s)=>0===s||void 0===e?t:Vt(t,e))).join(" "))).join(" ");function Tn(t,e){const s=t.style;s&&e&&("string"==typeof e?s.cssText+=";"+e:Object.entries(e).forEach((t=>{let[e,i]=t;return s.setProperty(e,i)})))}const On=(t,e)=>Math.floor(Math.random()*(e-t+1))+t;function kn(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const s=e.onComplete||C,i=new(v().XMLHttpRequest),r=e.signal,n=function(){i.abort()},o=function(){r&&r.removeEventListener("abort",n),i.onerror=i.ontimeout=C};if(r&&r.aborted)throw new c("request");return r&&r.addEventListener("abort",n,{once:!0}),i.onreadystatechange=function(){4===i.readyState&&(o(),s(i),i.onreadystatechange=C)},i.onerror=i.ontimeout=o,i.open("get",t,!0),i.send(),i}const Dn=(t,e)=>{let s=t._findCenterFromElement();t.transformMatrix&&((t=>{if(t.transformMatrix){const{scaleX:e,scaleY:s,angle:i,skewX:r}=Dt(t.transformMatrix);t.flipX=!1,t.flipY=!1,t.set(H,e),t.set(N,s),t.angle=i,t.skewX=r,t.skewY=0}})(t),s=s.transform(t.transformMatrix)),delete t.transformMatrix,e&&(t.scaleX*=e.scaleX,t.scaleY*=e.scaleY,t.cropX=e.cropX,t.cropY=e.cropY,s.x+=e.offsetLeft,s.y+=e.offsetTop,t.width=e.width,t.height=e.height),t.setPositionByOrigin(s,D,D)};var Mn=Object.freeze({__proto__:null,addTransformToObject:le,animate:Ks,animateColor:Js,applyTransformToObject:ue,calcAngleBetweenVectors:_s,calcDimensionsMatrix:Lt,calcPlaneChangeMatrix:pe,calcVectorRotation:xs,cancelAnimFrame:dt,capValue:Ms,composeMatrix:Rt,copyCanvasElement:t=>{var e;const s=vt(t);return null===(e=s.getContext("2d"))||void 0===e||e.drawImage(t,0,0),s},cos:rt,createCanvasElement:pt,createImage:mt,createRotateMatrix:Pt,createScaleMatrix:Et,createSkewXMatrix:jt,createSkewYMatrix:Ft,createTranslateMatrix:Mt,createVector:vs,crossProduct:Ss,degreesToRadians:xt,dotProduct:ws,ease:Bs,enlivenObjectEnlivables:Xt,enlivenObjects:Bt,findScaleToCover:Kr,findScaleToFit:qr,getBoundsOfCurve:sn,getOrthonormalVector:bs,getPathSegmentsInfo:mn,getPointOnPath:vn,getPointer:ne,getRandomInt:On,getRegularPolygonPath:(t,e)=>{const s=2*Math.PI/t;let i=-b;t%2==0&&(i+=s/2);const r=new Array(t+1);for(let n=0;n<t;n++){const t=n*s+i,{x:o,y:a}=new ot(rt(t),nt(t)).scalarMultiply(e);r[n]=[0===n?"M":"L",o,a]}return r[t]=["Z"],r},getSmoothPathFromPoints:Sn,getSvgAttributes:t=>{const e=["instantiated_by_use","style","id","class"];switch(t){case"linearGradient":return e.concat(["x1","y1","x2","y2","gradientUnits","gradientTransform"]);case"radialGradient":return e.concat(["gradientUnits","gradientTransform","cx","cy","r","fx","fy","fr"]);case"stop":return e.concat(["offset","stop-color","stop-opacity"])}return e},getUnitVector:Cs,groupSVGElements:(t,e)=>t&&1===t.length?t[0]:new Ur(t,e),hasStyleChanged:Ki,invertTransform:wt,isBetweenVectors:Ts,isIdentityMatrix:bt,isTouchEvent:oe,isTransparent:Ri,joinPath:wn,loadImage:It,magnitude:ys,makeBoundingBoxFromPoints:he,makePathSimpler:nn,matrixToSVG:zt,mergeClipPaths:(t,e)=>{var s;let i=t,r=e;i.inverted&&!r.inverted&&(i=e,r=t),ye(r,null===(s=r.group)||void 0===s?void 0:s.calcTransformMatrix(),i.calcTransformMatrix());const n=i.inverted&&r.inverted;return n&&(i.inverted=r.inverted=!1),new Ur([i],{clipPath:r,inverted:n})},multiplyTransformMatrices:Tt,multiplyTransformMatrixArray:Ot,parsePath:bn,parsePreserveAspectRatioAttribute:Xe,parseUnit:Be,pick:Yt,projectStrokeOnPoints:Wi,qrDecompose:Dt,radiansToDegrees:Ct,removeFromArray:it,removeTransformFromObject:(t,e)=>{const s=wt(e),i=Tt(s,t.calcOwnMatrix());ue(t,i)},removeTransformMatrixForSvgParsing:Dn,request:kn,requestAnimFrame:ut,resetObjectTransform:de,rotatePoint:(t,e,s)=>t.rotate(s,e),rotateVector:ms,saveObjectTransform:ge,sendObjectToPlane:ye,sendPointToPlane:me,sendVectorToPlane:ve,setStyle:Tn,sin:nt,sizeAfterTransform:fe,string:qi,stylesFromArray:Qi,stylesToArray:Ji,toBlob:_t,toDataURL:yt,toFixed:Vt,transformPath:(t,e,s)=>(s&&(e=Tt(e,[1,0,0,1,-s.x,-s.y])),t.map((t=>{const s=[...t];for(let i=1;i<t.length-1;i+=2){const{x:r,y:n}=St({x:t[i],y:t[i+1]},e);s[i]=r,s[i+1]=n}return s}))),transformPoint:St});class Pn extends te{constructor(e){let{allowTouchScrolling:s=!1,containerClass:i=""}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};super(e),t(this,"upper",void 0),t(this,"container",void 0);const{el:r}=this.lower,n=this.createUpperCanvas();this.upper={el:n,ctx:n.getContext("2d")},this.applyCanvasStyle(r,{allowTouchScrolling:s}),this.applyCanvasStyle(n,{allowTouchScrolling:s,styles:{position:"absolute",left:"0",top:"0"}});const o=this.createContainerElement();o.classList.add(i),r.parentNode&&r.parentNode.replaceChild(o,r),o.append(r,n),this.container=o}createUpperCanvas(){const{el:t}=this.lower,e=pt();return e.className=t.className,e.classList.remove("lower-canvas"),e.classList.add("upper-canvas"),e.setAttribute("data-fabric","top"),e.style.cssText=t.style.cssText,e.setAttribute("draggable","true"),e}createContainerElement(){const t=m().createElement("div");return t.setAttribute("data-fabric","wrapper"),Tn(t,{position:"relative"}),$t(t),t}applyCanvasStyle(t,e){const{styles:i,allowTouchScrolling:r}=e;Tn(t,s(s({},i),{},{"touch-action":r?"manipulation":j})),$t(t)}setDimensions(t,e){super.setDimensions(t,e);const{el:s,ctx:i}=this.upper;Qt(s,i,t,e)}setCSSDimensions(t){super.setCSSDimensions(t),Zt(this.upper.el,t),Zt(this.container,t)}cleanupDOM(t){const e=this.container,{el:s}=this.lower,{el:i}=this.upper;super.cleanupDOM(t),e.removeChild(i),e.removeChild(s),e.parentNode&&e.parentNode.replaceChild(s,e)}dispose(){super.dispose(),p().dispose(this.upper.el),delete this.upper,delete this.container}}class En extends ie{constructor(){super(...arguments),t(this,"targets",[]),t(this,"_hoveredTargets",[]),t(this,"_currentTransform",null),t(this,"_groupSelector",null),t(this,"contextTopDirty",!1)}static getDefaults(){return s(s({},super.getDefaults()),En.ownDefaults)}get upperCanvasEl(){var t;return null===(t=this.elements.upper)||void 0===t?void 0:t.el}get contextTop(){var t;return null===(t=this.elements.upper)||void 0===t?void 0:t.ctx}get wrapperEl(){return this.elements.container}initElements(t){this.elements=new Pn(t,{allowTouchScrolling:this.allowTouchScrolling,containerClass:this.containerClass}),this._createCacheCanvas()}_onObjectAdded(t){this._objectsToRender=void 0,super._onObjectAdded(t)}_onObjectRemoved(t){this._objectsToRender=void 0,t===this._activeObject&&(this.fire("before:selection:cleared",{deselected:[t]}),this._discardActiveObject(),this.fire("selection:cleared",{deselected:[t]}),t.fire("deselected",{target:t})),t===this._hoveredTarget&&(this._hoveredTarget=void 0,this._hoveredTargets=[]),super._onObjectRemoved(t)}_onStackOrderChanged(){this._objectsToRender=void 0,super._onStackOrderChanged()}_chooseObjectsToRender(){const t=this._activeObject;return!this.preserveObjectStacking&&t?this._objects.filter((e=>!e.group&&e!==t)).concat(t):this._objects}renderAll(){this.cancelRequestedRender(),this.destroyed||(!this.contextTopDirty||this._groupSelector||this.isDrawingMode||(this.clearContext(this.contextTop),this.contextTopDirty=!1),this.hasLostContext&&(this.renderTopLayer(this.contextTop),this.hasLostContext=!1),!this._objectsToRender&&(this._objectsToRender=this._chooseObjectsToRender()),this.renderCanvas(this.getContext(),this._objectsToRender))}renderTopLayer(t){t.save(),this.isDrawingMode&&this._isCurrentlyDrawing&&(this.freeDrawingBrush&&this.freeDrawingBrush._render(),this.contextTopDirty=!0),this.selection&&this._groupSelector&&(this._drawSelection(t),this.contextTopDirty=!0),t.restore()}renderTop(){const t=this.contextTop;this.clearContext(t),this.renderTopLayer(t),this.fire("after:render",{ctx:t})}setTargetFindTolerance(t){t=Math.round(t),this.targetFindTolerance=t;const e=this.getRetinaScaling(),s=Math.ceil((2*t+1)*e);this.pixelFindCanvasEl.width=this.pixelFindCanvasEl.height=s,this.pixelFindContext.scale(e,e)}isTargetTransparent(t,e,s){const i=this.targetFindTolerance,r=this.pixelFindContext;this.clearContext(r),r.save(),r.translate(-e+i,-s+i),r.transform(...this.viewportTransform);const n=t.selectionBackgroundColor;t.selectionBackgroundColor="",t.render(r),t.selectionBackgroundColor=n,r.restore();const o=Math.round(i*this.getRetinaScaling());return Ri(r,o,o,o)}_isSelectionKeyPressed(t){const e=this.selectionKey;return!!e&&(Array.isArray(e)?!!e.find((e=>!!e&&!0===t[e])):t[e])}_shouldClearSelection(t,e){const s=this.getActiveObjects(),i=this._activeObject;return!!(!e||e&&i&&s.length>1&&-1===s.indexOf(e)&&i!==e&&!this._isSelectionKeyPressed(t)||e&&!e.evented||e&&!e.selectable&&i&&i!==e)}_shouldCenterTransform(t,e,s){if(!t)return;let i;return e===G||e===H||e===N||e===Y?i=this.centeredScaling||t.centeredScaling:e===B&&(i=this.centeredRotation||t.centeredRotation),i?!s:s}_getOriginFromCorner(t,e){const s={x:t.originX,y:t.originY};return e?(["ml","tl","bl"].includes(e)?s.x=A:["mr","tr","br"].includes(e)&&(s.x=M),["tl","mt","tr"].includes(e)?s.y=E:["bl","mb","br"].includes(e)&&(s.y=P),s):s}_setupCurrentTransform(t,e,i){var r;const n=e.group?me(this.getScenePoint(t),void 0,e.group.calcTransformMatrix()):this.getScenePoint(t),{key:o="",control:a}=e.getActiveControl()||{},h=i&&a?null===(r=a.getActionHandler(t,e,a))||void 0===r?void 0:r.bind(a):Me,c=((t,e,s,i)=>{if(!e||!t)return"drag";const r=i.controls[e];return r.getActionName(s,r,i)})(i,o,t,e),l=t[this.centeredKey],u=this._shouldCenterTransform(e,c,l)?{x:D,y:D}:this._getOriginFromCorner(e,o),d={target:e,action:c,actionHandler:h,actionPerformed:!1,corner:o,scaleX:e.scaleX,scaleY:e.scaleY,skewX:e.skewX,skewY:e.skewY,offsetX:n.x-e.left,offsetY:n.y-e.top,originX:u.x,originY:u.y,ex:n.x,ey:n.y,lastX:n.x,lastY:n.y,theta:xt(e.angle),width:e.width,height:e.height,shiftKey:t.shiftKey,altKey:l,original:s(s({},ge(e)),{},{originX:u.x,originY:u.y})};this._currentTransform=d,this.fire("before:transform",{e:t,transform:d})}setCursor(t){this.upperCanvasEl.style.cursor=t}_drawSelection(t){const{x:e,y:s,deltaX:i,deltaY:r}=this._groupSelector,n=new ot(e,s).transform(this.viewportTransform),o=new ot(e+i,s+r).transform(this.viewportTransform),a=this.selectionLineWidth/2;let h=Math.min(n.x,o.x),c=Math.min(n.y,o.y),l=Math.max(n.x,o.x),u=Math.max(n.y,o.y);this.selectionColor&&(t.fillStyle=this.selectionColor,t.fillRect(h,c,l-h,u-c)),this.selectionLineWidth&&this.selectionBorderColor&&(t.lineWidth=this.selectionLineWidth,t.strokeStyle=this.selectionBorderColor,h+=a,c+=a,l-=a,u-=a,Li.prototype._setLineDash.call(this,t,this.selectionDashArray),t.strokeRect(h,c,l-h,u-c))}findTarget(t){if(this.skipTargetFind)return;const e=this.getViewportPoint(t),s=this._activeObject,i=this.getActiveObjects();if(this.targets=[],s&&i.length>=1){if(s.findControl(e,oe(t)))return s;if(i.length>1&&this.searchPossibleTargets([s],e))return s;if(s===this.searchPossibleTargets([s],e)){if(this.preserveObjectStacking){const i=this.targets;this.targets=[];const r=this.searchPossibleTargets(this._objects,e);return t[this.altSelectionKey]&&r&&r!==s?(this.targets=i,s):r}return s}}return this.searchPossibleTargets(this._objects,e)}_pointIsInObjectSelectionArea(t,e){let s=t.getCoords();const i=this.getZoom(),r=t.padding/i;if(r){const[t,e,i,n]=s,o=Math.atan2(e.y-t.y,e.x-t.x),a=rt(o)*r,h=nt(o)*r,c=a+h,l=a-h;s=[new ot(t.x-l,t.y-c),new ot(e.x+c,e.y-l),new ot(i.x+l,i.y+c),new ot(n.x-c,n.y+l)]}return Qs.isPointInPolygon(e,s)}_checkTarget(t,e){if(t&&t.visible&&t.evented&&this._pointIsInObjectSelectionArea(t,me(e,void 0,this.viewportTransform))){if(!this.perPixelTargetFind&&!t.perPixelTargetFind||t.isEditing)return!0;if(!this.isTargetTransparent(t,e.x,e.y))return!0}return!1}_searchPossibleTargets(t,e){let s=t.length;for(;s--;){const i=t[s];if(this._checkTarget(i,e)){if(ht(i)&&i.subTargetCheck){const t=this._searchPossibleTargets(i._objects,e);t&&this.targets.push(t)}return i}}}searchPossibleTargets(t,e){const s=this._searchPossibleTargets(t,e);if(s&&ht(s)&&s.interactive&&this.targets[0]){const t=this.targets;for(let e=t.length-1;e>0;e--){const s=t[e];if(!ht(s)||!s.interactive)return s}return t[0]}return s}getViewportPoint(t){return this._pointer?this._pointer:this.getPointer(t,!0)}getScenePoint(t){return this._absolutePointer?this._absolutePointer:this.getPointer(t)}getPointer(t){let e=arguments.length>1&&void 0!==arguments[1]&&arguments[1];const s=this.upperCanvasEl,i=s.getBoundingClientRect();let r=ne(t),n=i.width||0,o=i.height||0;n&&o||(P in i&&E in i&&(o=Math.abs(i.top-i.bottom)),A in i&&M in i&&(n=Math.abs(i.right-i.left))),this.calcOffset(),r.x=r.x-this._offset.left,r.y=r.y-this._offset.top,e||(r=me(r,void 0,this.viewportTransform));const a=this.getRetinaScaling();1!==a&&(r.x/=a,r.y/=a);const h=0===n||0===o?new ot(1,1):new ot(s.width/n,s.height/o);return r.multiply(h)}_setDimensionsImpl(t,e){this._resetTransformEventData(),super._setDimensionsImpl(t,e),this._isCurrentlyDrawing&&this.freeDrawingBrush&&this.freeDrawingBrush._setBrushStyles(this.contextTop)}_createCacheCanvas(){this.pixelFindCanvasEl=pt(),this.pixelFindContext=this.pixelFindCanvasEl.getContext("2d",{willReadFrequently:!0}),this.setTargetFindTolerance(this.targetFindTolerance)}getTopContext(){return this.elements.upper.ctx}getSelectionContext(){return this.elements.upper.ctx}getSelectionElement(){return this.elements.upper.el}getActiveObject(){return this._activeObject}getActiveObjects(){const t=this._activeObject;return Ut(t)?t.getObjects():t?[t]:[]}_fireSelectionEvents(t,e){let s=!1,i=!1;const r=this.getActiveObjects(),n=[],o=[];t.forEach((t=>{r.includes(t)||(s=!0,t.fire("deselected",{e:e,target:t}),o.push(t))})),r.forEach((i=>{t.includes(i)||(s=!0,i.fire("selected",{e:e,target:i}),n.push(i))})),t.length>0&&r.length>0?(i=!0,s&&this.fire("selection:updated",{e:e,selected:n,deselected:o})):r.length>0?(i=!0,this.fire("selection:created",{e:e,selected:n})):t.length>0&&(i=!0,this.fire("selection:cleared",{e:e,deselected:o})),i&&(this._objectsToRender=void 0)}setActiveObject(t,e){const s=this.getActiveObjects(),i=this._setActiveObject(t,e);return this._fireSelectionEvents(s,e),i}_setActiveObject(t,e){const s=this._activeObject;return s!==t&&(!(!this._discardActiveObject(e,t)&&this._activeObject)&&(!t.onSelect({e:e})&&(this._activeObject=t,Ut(t)&&s!==t&&t.set("canvas",this),t.setCoords(),!0)))}_discardActiveObject(t,e){const s=this._activeObject;return!!s&&(!s.onDeselect({e:t,object:e})&&(this._currentTransform&&this._currentTransform.target===s&&this.endCurrentTransform(t),Ut(s)&&s===this._hoveredTarget&&(this._hoveredTarget=void 0),this._activeObject=void 0,!0))}discardActiveObject(t){const e=this.getActiveObjects(),s=this.getActiveObject();e.length&&this.fire("before:selection:cleared",{e:t,deselected:[s]});const i=this._discardActiveObject(t);return this._fireSelectionEvents(e,t),i}endCurrentTransform(t){const e=this._currentTransform;this._finalizeCurrentTransform(t),e&&e.target&&(e.target.isMoving=!1),this._currentTransform=null}_finalizeCurrentTransform(t){const e=this._currentTransform,s=e.target,i={e:t,target:s,transform:e,action:e.action};s._scaling&&(s._scaling=!1),s.setCoords(),e.actionPerformed&&(this.fire("object:modified",i),s.fire(Q,i))}setViewportTransform(t){super.setViewportTransform(t);const e=this._activeObject;e&&e.setCoords()}destroy(){const t=this._activeObject;Ut(t)&&(t.removeAll(),t.dispose()),delete this._activeObject,super.destroy(),this.pixelFindContext=null,this.pixelFindCanvasEl=void 0}clear(){this.discardActiveObject(),this._activeObject=void 0,this.clearContext(this.contextTop),super.clear()}drawControls(t){const e=this._activeObject;e&&e._renderControls(t)}_toObject(t,e,s){const i=this._realizeGroupTransformOnObject(t),r=super._toObject(t,e,s);return t.set(i),r}_realizeGroupTransformOnObject(t){const{group:e}=t;if(e&&Ut(e)&&this._activeObject===e){const s=Yt(t,["angle","flipX","flipY",M,H,N,U,q,P]);return le(t,e.calcOwnMatrix()),s}return{}}_setSVGObject(t,e,s){const i=this._realizeGroupTransformOnObject(e);super._setSVGObject(t,e,s),e.set(i)}}t(En,"ownDefaults",{uniformScaling:!0,uniScaleKey:"shiftKey",centeredScaling:!1,centeredRotation:!1,centeredKey:"altKey",altActionKey:"shiftKey",selection:!0,selectionKey:"shiftKey",selectionColor:"rgba(100, 100, 255, 0.3)",selectionDashArray:[],selectionBorderColor:"rgba(255, 255, 255, 0.3)",selectionLineWidth:1,selectionFullyContained:!1,hoverCursor:"move",moveCursor:"move",defaultCursor:"default",freeDrawingCursor:"crosshair",notAllowedCursor:"not-allowed",perPixelTargetFind:!1,targetFindTolerance:0,skipTargetFind:!1,stopContextMenu:!1,fireRightClick:!1,fireMiddleClick:!1,enablePointerEvents:!1,containerClass:"canvas-container",preserveObjectStacking:!1});class An{constructor(e){t(this,"targets",[]),t(this,"__disposer",void 0);const s=()=>{const{hiddenTextarea:t}=e.getActiveObject()||{};t&&t.focus()},i=e.upperCanvasEl;i.addEventListener("click",s),this.__disposer=()=>i.removeEventListener("click",s)}exitTextEditing(){this.target=void 0,this.targets.forEach((t=>{t.isEditing&&t.exitEditing()}))}add(t){this.targets.push(t)}remove(t){this.unregister(t),it(this.targets,t)}register(t){this.target=t}unregister(t){t===this.target&&(this.target=void 0)}onMouseMove(t){var e;(null===(e=this.target)||void 0===e?void 0:e.isEditing)&&this.target.updateSelectionOnMouseMove(t)}clear(){this.targets=[],this.target=void 0}dispose(){this.clear(),this.__disposer(),delete this.__disposer}}const jn=["target","oldTarget","fireCanvas","e"],Fn={passive:!1},Ln=(t,e)=>{const s=t.getViewportPoint(e),i=t.getScenePoint(e);return{viewportPoint:s,scenePoint:i,pointer:s,absolutePointer:i}},Rn=function(t){for(var e=arguments.length,s=new Array(e>1?e-1:0),i=1;i<e;i++)s[i-1]=arguments[i];return t.addEventListener(...s)},In=function(t){for(var e=arguments.length,s=new Array(e>1?e-1:0),i=1;i<e;i++)s[i-1]=arguments[i];return t.removeEventListener(...s)},Bn={mouse:{in:"over",out:"out",targetIn:"mouseover",targetOut:"mouseout",canvasIn:"mouse:over",canvasOut:"mouse:out"},drag:{in:"enter",out:"leave",targetIn:"dragenter",targetOut:"dragleave",canvasIn:"drag:enter",canvasOut:"drag:leave"}};class Xn extends En{constructor(e){super(e,arguments.length>1&&void 0!==arguments[1]?arguments[1]:{}),t(this,"_isClick",void 0),t(this,"textEditingManager",new An(this)),["_onMouseDown","_onTouchStart","_onMouseMove","_onMouseUp","_onTouchEnd","_onResize","_onMouseWheel","_onMouseOut","_onMouseEnter","_onContextMenu","_onClick","_onDragStart","_onDragEnd","_onDragProgress","_onDragOver","_onDragEnter","_onDragLeave","_onDrop"].forEach((t=>{this[t]=this[t].bind(this)})),this.addOrRemove(Rn,"add")}_getEventPrefix(){return this.enablePointerEvents?"pointer":"mouse"}addOrRemove(t,e){const s=this.upperCanvasEl,i=this._getEventPrefix();t(Jt(s),"resize",this._onResize),t(s,i+"down",this._onMouseDown),t(s,"".concat(i,"move"),this._onMouseMove,Fn),t(s,"".concat(i,"out"),this._onMouseOut),t(s,"".concat(i,"enter"),this._onMouseEnter),t(s,"wheel",this._onMouseWheel,{passive:!1}),t(s,"contextmenu",this._onContextMenu),t(s,"click",this._onClick),t(s,"dblclick",this._onClick),t(s,"dragstart",this._onDragStart),t(s,"dragend",this._onDragEnd),t(s,"dragover",this._onDragOver),t(s,"dragenter",this._onDragEnter),t(s,"dragleave",this._onDragLeave),t(s,"drop",this._onDrop),this.enablePointerEvents||t(s,"touchstart",this._onTouchStart,Fn)}removeListeners(){this.addOrRemove(In,"remove");const t=this._getEventPrefix(),e=Kt(this.upperCanvasEl);In(e,"".concat(t,"up"),this._onMouseUp),In(e,"touchend",this._onTouchEnd,Fn),In(e,"".concat(t,"move"),this._onMouseMove,Fn),In(e,"touchmove",this._onMouseMove,Fn),clearTimeout(this._willAddMouseDown)}_onMouseWheel(t){this.__onMouseWheel(t)}_onMouseOut(t){const e=this._hoveredTarget,i=s({e:t},Ln(this,t));this.fire("mouse:out",s(s({},i),{},{target:e})),this._hoveredTarget=void 0,e&&e.fire("mouseout",s({},i)),this._hoveredTargets.forEach((t=>{this.fire("mouse:out",s(s({},i),{},{target:t})),t&&t.fire("mouseout",s({},i))})),this._hoveredTargets=[]}_onMouseEnter(t){this._currentTransform||this.findTarget(t)||(this.fire("mouse:over",s({e:t},Ln(this,t))),this._hoveredTarget=void 0,this._hoveredTargets=[])}_onDragStart(t){this._isClick=!1;const e=this.getActiveObject();if(e&&e.onDragStart(t)){this._dragSource=e;const s={e:t,target:e};return this.fire("dragstart",s),e.fire("dragstart",s),void Rn(this.upperCanvasEl,"drag",this._onDragProgress)}ae(t)}_renderDragEffects(t,e,s){let i=!1;const r=this._dropTarget;r&&r!==e&&r!==s&&(r.clearContextTop(),i=!0),null==e||e.clearContextTop(),s!==e&&(null==s||s.clearContextTop());const n=this.contextTop;n.save(),n.transform(...this.viewportTransform),e&&(n.save(),e.transform(n),e.renderDragSourceEffect(t),n.restore(),i=!0),s&&(n.save(),s.transform(n),s.renderDropTargetEffect(t),n.restore(),i=!0),n.restore(),i&&(this.contextTopDirty=!0)}_onDragEnd(t){const e=!!t.dataTransfer&&t.dataTransfer.dropEffect!==j,s=e?this._activeObject:void 0,i={e:t,target:this._dragSource,subTargets:this.targets,dragSource:this._dragSource,didDrop:e,dropTarget:s};In(this.upperCanvasEl,"drag",this._onDragProgress),this.fire("dragend",i),this._dragSource&&this._dragSource.fire("dragend",i),delete this._dragSource,this._onMouseUp(t)}_onDragProgress(t){const e={e:t,target:this._dragSource,dragSource:this._dragSource,dropTarget:this._draggedoverTarget};this.fire("drag",e),this._dragSource&&this._dragSource.fire("drag",e)}findDragTargets(t){this.targets=[];return{target:this._searchPossibleTargets(this._objects,this.getViewportPoint(t)),targets:[...this.targets]}}_onDragOver(t){const e="dragover",{target:s,targets:i}=this.findDragTargets(t),r=this._dragSource,n={e:t,target:s,subTargets:i,dragSource:r,canDrop:!1,dropTarget:void 0};let o;this.fire(e,n),this._fireEnterLeaveEvents(s,n),s&&(s.canDrop(t)&&(o=s),s.fire(e,n));for(let s=0;s<i.length;s++){const r=i[s];r.canDrop(t)&&(o=r),r.fire(e,n)}this._renderDragEffects(t,r,o),this._dropTarget=o}_onDragEnter(t){const{target:e,targets:s}=this.findDragTargets(t),i={e:t,target:e,subTargets:s,dragSource:this._dragSource};this.fire("dragenter",i),this._fireEnterLeaveEvents(e,i)}_onDragLeave(t){const e={e:t,target:this._draggedoverTarget,subTargets:this.targets,dragSource:this._dragSource};this.fire("dragleave",e),this._fireEnterLeaveEvents(void 0,e),this._renderDragEffects(t,this._dragSource),this._dropTarget=void 0,this.targets=[],this._hoveredTargets=[]}_onDrop(t){const{target:e,targets:i}=this.findDragTargets(t),r=this._basicEventHandler("drop:before",s({e:t,target:e,subTargets:i,dragSource:this._dragSource},Ln(this,t)));r.didDrop=!1,r.dropTarget=void 0,this._basicEventHandler("drop",r),this.fire("drop:after",r)}_onContextMenu(t){const e=this.findTarget(t),s=this.targets||[],i=this._basicEventHandler("contextmenu:before",{e:t,target:e,subTargets:s});return this.stopContextMenu&&ae(t),this._basicEventHandler("contextmenu",i),!1}_onClick(t){const e=t.detail;e>3||e<2||(this._cacheTransformEventData(t),2==e&&"dblclick"===t.type&&this._handleEvent(t,"dblclick"),3==e&&this._handleEvent(t,"tripleclick"),this._resetTransformEventData())}getPointerId(t){const e=t.changedTouches;return e?e[0]&&e[0].identifier:this.enablePointerEvents?t.pointerId:-1}_isMainEvent(t){return!0===t.isPrimary||!1!==t.isPrimary&&("touchend"===t.type&&0===t.touches.length||(!t.changedTouches||t.changedTouches[0].identifier===this.mainTouchId))}_onTouchStart(t){let e=!this.allowTouchScrolling;const s=this._activeObject;void 0===this.mainTouchId&&(this.mainTouchId=this.getPointerId(t)),this.__onMouseDown(t),(this.isDrawingMode||s&&this._target===s)&&(e=!0),e&&t.preventDefault(),this._resetTransformEventData();const i=this.upperCanvasEl,r=this._getEventPrefix(),n=Kt(i);Rn(n,"touchend",this._onTouchEnd,Fn),e&&Rn(n,"touchmove",this._onMouseMove,Fn),In(i,"".concat(r,"down"),this._onMouseDown)}_onMouseDown(t){this.__onMouseDown(t),this._resetTransformEventData();const e=this.upperCanvasEl,s=this._getEventPrefix();In(e,"".concat(s,"move"),this._onMouseMove,Fn);const i=Kt(e);Rn(i,"".concat(s,"up"),this._onMouseUp),Rn(i,"".concat(s,"move"),this._onMouseMove,Fn)}_onTouchEnd(t){if(t.touches.length>0)return;this.__onMouseUp(t),this._resetTransformEventData(),delete this.mainTouchId;const e=this._getEventPrefix(),s=Kt(this.upperCanvasEl);In(s,"touchend",this._onTouchEnd,Fn),In(s,"touchmove",this._onMouseMove,Fn),this._willAddMouseDown&&clearTimeout(this._willAddMouseDown),this._willAddMouseDown=setTimeout((()=>{Rn(this.upperCanvasEl,"".concat(e,"down"),this._onMouseDown),this._willAddMouseDown=0}),400)}_onMouseUp(t){this.__onMouseUp(t),this._resetTransformEventData();const e=this.upperCanvasEl,s=this._getEventPrefix();if(this._isMainEvent(t)){const t=Kt(this.upperCanvasEl);In(t,"".concat(s,"up"),this._onMouseUp),In(t,"".concat(s,"move"),this._onMouseMove,Fn),Rn(e,"".concat(s,"move"),this._onMouseMove,Fn)}}_onMouseMove(t){const e=this.getActiveObject();!this.allowTouchScrolling&&(!e||!e.shouldStartDragging(t))&&t.preventDefault&&t.preventDefault(),this.__onMouseMove(t)}_onResize(){this.calcOffset(),this._resetTransformEventData()}_shouldRender(t){const e=this.getActiveObject();return!!e!=!!t||e&&t&&e!==t}__onMouseUp(t){var e;this._cacheTransformEventData(t),this._handleEvent(t,"up:before");const s=this._currentTransform,i=this._isClick,r=this._target,{button:n}=t;if(n)return(this.fireMiddleClick&&1===n||this.fireRightClick&&2===n)&&this._handleEvent(t,"up"),void this._resetTransformEventData();if(this.isDrawingMode&&this._isCurrentlyDrawing)return void this._onMouseUpInDrawingMode(t);if(!this._isMainEvent(t))return;let o,a,h=!1;if(s&&(this._finalizeCurrentTransform(t),h=s.actionPerformed),!i){const e=r===this._activeObject;this.handleSelection(t),h||(h=this._shouldRender(r)||!e&&r===this._activeObject)}if(r){const e=r.findControl(this.getViewportPoint(t),oe(t)),{key:i,control:n}=e||{};if(a=i,r.selectable&&r!==this._activeObject&&"up"===r.activeOn)this.setActiveObject(r,t),h=!0;else if(n){const e=n.getMouseUpHandler(t,r,n);e&&(o=this.getScenePoint(t),e.call(n,t,s,o.x,o.y))}r.isMoving=!1}if(s&&(s.target!==r||s.corner!==a)){const e=s.target&&s.target.controls[s.corner],i=e&&e.getMouseUpHandler(t,s.target,e);o=o||this.getScenePoint(t),i&&i.call(e,t,s,o.x,o.y)}this._setCursorFromEvent(t,r),this._handleEvent(t,"up"),this._groupSelector=null,this._currentTransform=null,r&&(r.__corner=void 0),h?this.requestRenderAll():i||null!==(e=this._activeObject)&&void 0!==e&&e.isEditing||this.renderTop()}_basicEventHandler(t,e){const{target:s,subTargets:i=[]}=e;this.fire(t,e),s&&s.fire(t,e);for(let r=0;r<i.length;r++)i[r]!==s&&i[r].fire(t,e);return e}_handleEvent(t,e,i){const r=this._target,n=this.targets||[],o=s(s(s({e:t,target:r,subTargets:n},Ln(this,t)),{},{transform:this._currentTransform},"up:before"===e||"up"===e?{isClick:this._isClick,currentTarget:this.findTarget(t),currentSubTargets:this.targets}:{}),"down:before"===e||"down"===e?i:{});this.fire("mouse:".concat(e),o),r&&r.fire("mouse".concat(e),o);for(let t=0;t<n.length;t++)n[t]!==r&&n[t].fire("mouse".concat(e),o)}_onMouseDownInDrawingMode(t){this._isCurrentlyDrawing=!0,this.getActiveObject()&&(this.discardActiveObject(t),this.requestRenderAll());const e=this.getScenePoint(t);this.freeDrawingBrush&&this.freeDrawingBrush.onMouseDown(e,{e:t,pointer:e}),this._handleEvent(t,"down",{alreadySelected:!1})}_onMouseMoveInDrawingMode(t){if(this._isCurrentlyDrawing){const e=this.getScenePoint(t);this.freeDrawingBrush&&this.freeDrawingBrush.onMouseMove(e,{e:t,pointer:e})}this.setCursor(this.freeDrawingCursor),this._handleEvent(t,"move")}_onMouseUpInDrawingMode(t){const e=this.getScenePoint(t);this.freeDrawingBrush?this._isCurrentlyDrawing=!!this.freeDrawingBrush.onMouseUp({e:t,pointer:e}):this._isCurrentlyDrawing=!1,this._handleEvent(t,"up")}__onMouseDown(t){this._isClick=!0,this._cacheTransformEventData(t),this._handleEvent(t,"down:before");let e=this._target,s=!!e&&e===this._activeObject;const{button:i}=t;if(i)return(this.fireMiddleClick&&1===i||this.fireRightClick&&2===i)&&this._handleEvent(t,"down",{alreadySelected:s}),void this._resetTransformEventData();if(this.isDrawingMode)return void this._onMouseDownInDrawingMode(t);if(!this._isMainEvent(t))return;if(this._currentTransform)return;let r=this._shouldRender(e),n=!1;if(this.handleMultiSelection(t,e)?(e=this._activeObject,n=!0,r=!0):this._shouldClearSelection(t,e)&&this.discardActiveObject(t),this.selection&&(!e||!e.selectable&&!e.isEditing&&e!==this._activeObject)){const e=this.getScenePoint(t);this._groupSelector={x:e.x,y:e.y,deltaY:0,deltaX:0}}if(s=!!e&&e===this._activeObject,e){e.selectable&&"down"===e.activeOn&&this.setActiveObject(e,t);const i=e.findControl(this.getViewportPoint(t),oe(t));if(e===this._activeObject&&(i||!n)){this._setupCurrentTransform(t,e,s);const r=i?i.control:void 0,n=this.getScenePoint(t),o=r&&r.getMouseDownHandler(t,e,r);o&&o.call(r,t,this._currentTransform,n.x,n.y)}}r&&(this._objectsToRender=void 0),this._handleEvent(t,"down",{alreadySelected:s}),r&&this.requestRenderAll()}_resetTransformEventData(){this._target=this._pointer=this._absolutePointer=void 0}_cacheTransformEventData(t){this._resetTransformEventData(),this._pointer=this.getViewportPoint(t),this._absolutePointer=me(this._pointer,void 0,this.viewportTransform),this._target=this._currentTransform?this._currentTransform.target:this.findTarget(t)}__onMouseMove(t){if(this._isClick=!1,this._cacheTransformEventData(t),this._handleEvent(t,"move:before"),this.isDrawingMode)return void this._onMouseMoveInDrawingMode(t);if(!this._isMainEvent(t))return;const e=this._groupSelector;if(e){const s=this.getScenePoint(t);e.deltaX=s.x-e.x,e.deltaY=s.y-e.y,this.renderTop()}else if(this._currentTransform)this._transformObject(t);else{const e=this.findTarget(t);this._setCursorFromEvent(t,e),this._fireOverOutEvents(t,e)}this.textEditingManager.onMouseMove(t),this._handleEvent(t,"move"),this._resetTransformEventData()}_fireOverOutEvents(t,e){const s=this._hoveredTarget,i=this._hoveredTargets,r=this.targets,n=Math.max(i.length,r.length);this.fireSyntheticInOutEvents("mouse",{e:t,target:e,oldTarget:s,fireCanvas:!0});for(let o=0;o<n;o++)r[o]===e||i[o]&&i[o]===s||this.fireSyntheticInOutEvents("mouse",{e:t,target:r[o],oldTarget:i[o]});this._hoveredTarget=e,this._hoveredTargets=this.targets.concat()}_fireEnterLeaveEvents(t,e){const i=this._draggedoverTarget,r=this._hoveredTargets,n=this.targets,o=Math.max(r.length,n.length);this.fireSyntheticInOutEvents("drag",s(s({},e),{},{target:t,oldTarget:i,fireCanvas:!0}));for(let t=0;t<o;t++)this.fireSyntheticInOutEvents("drag",s(s({},e),{},{target:n[t],oldTarget:r[t]}));this._draggedoverTarget=t}fireSyntheticInOutEvents(t,e){let{target:r,oldTarget:n,fireCanvas:o,e:a}=e,h=i(e,jn);const{targetIn:c,targetOut:l,canvasIn:u,canvasOut:d}=Bn[t],g=n!==r;if(n&&g){const t=s(s({},h),{},{e:a,target:n,nextTarget:r},Ln(this,a));o&&this.fire(d,t),n.fire(l,t)}if(r&&g){const t=s(s({},h),{},{e:a,target:r,previousTarget:n},Ln(this,a));o&&this.fire(u,t),r.fire(c,t)}}__onMouseWheel(t){this._cacheTransformEventData(t),this._handleEvent(t,"wheel"),this._resetTransformEventData()}_transformObject(t){const e=this.getScenePoint(t),s=this._currentTransform,i=s.target,r=i.group?me(e,void 0,i.group.calcTransformMatrix()):e;s.shiftKey=t.shiftKey,s.altKey=!!this.centeredKey&&t[this.centeredKey],this._performTransformAction(t,s,r),s.actionPerformed&&this.requestRenderAll()}_performTransformAction(t,e,s){const{action:i,actionHandler:r,target:n}=e,o=!!r&&r(t,e,s.x,s.y);o&&n.setCoords(),"drag"===i&&o&&(e.target.isMoving=!0,this.setCursor(e.target.moveCursor||this.moveCursor)),e.actionPerformed=e.actionPerformed||o}_setCursorFromEvent(t,e){if(!e)return void this.setCursor(this.defaultCursor);let s=e.hoverCursor||this.hoverCursor;const i=Ut(this._activeObject)?this._activeObject:null,r=(!i||e.group!==i)&&e.findControl(this.getViewportPoint(t));if(r){const s=r.control;this.setCursor(s.cursorStyleHandler(t,s,e))}else e.subTargetCheck&&this.targets.concat().reverse().map((t=>{s=t.hoverCursor||s})),this.setCursor(s)}handleMultiSelection(t,e){const s=this._activeObject,i=Ut(s);if(s&&this._isSelectionKeyPressed(t)&&this.selection&&e&&e.selectable&&(s!==e||i)&&(i||!e.isDescendantOf(s)&&!s.isDescendantOf(e))&&!e.onSelect({e:t})&&!s.getActiveControl()){if(i){const i=s.getObjects();if(e===s){const s=this.getViewportPoint(t);if(!(e=this.searchPossibleTargets(i,s)||this.searchPossibleTargets(this._objects,s))||!e.selectable)return!1}e.group===s?(s.remove(e),this._hoveredTarget=e,this._hoveredTargets=[...this.targets],1===s.size()&&this._setActiveObject(s.item(0),t)):(s.multiSelectAdd(e),this._hoveredTarget=s,this._hoveredTargets=[...this.targets]),this._fireSelectionEvents(i,t)}else{s.isEditing&&s.exitEditing();const i=new(tt.getClass("ActiveSelection"))([],{canvas:this});i.multiSelectAdd(s,e),this._hoveredTarget=i,this._setActiveObject(i,t),this._fireSelectionEvents([s],t)}return!0}return!1}handleSelection(t){if(!this.selection||!this._groupSelector)return!1;const{x:e,y:s,deltaX:i,deltaY:r}=this._groupSelector,n=new ot(e,s),o=n.add(new ot(i,r)),a=n.min(o),h=n.max(o).subtract(a),c=this.collectObjects({left:a.x,top:a.y,width:h.x,height:h.y},{includeIntersecting:!this.selectionFullyContained}),l=n.eq(o)?c[0]?[c[0]]:[]:c.length>1?c.filter((e=>!e.onSelect({e:t}))).reverse():c;if(1===l.length)this.setActiveObject(l[0],t);else if(l.length>1){const e=tt.getClass("ActiveSelection");this.setActiveObject(new e(l,{canvas:this}),t)}return this._groupSelector=null,!0}toCanvasElement(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:1,e=arguments.length>1?arguments[1]:void 0;const{upper:s}=this.elements;s.ctx=void 0;const i=super.toCanvasElement(t,e);return s.ctx=s.el.getContext("2d"),i}clear(){this.textEditingManager.clear(),super.clear()}destroy(){this.removeListeners(),this.textEditingManager.dispose(),super.destroy()}}const Yn={x1:0,y1:0,x2:0,y2:0},Wn=s(s({},Yn),{},{r1:0,r2:0}),Vn=(t,e)=>isNaN(t)&&"number"==typeof e?e:t;function zn(t){return t&&/%$/.test(t)&&Number.isFinite(parseFloat(t))}function Gn(t,e){const s="number"==typeof t?t:"string"==typeof t?parseFloat(t)/(zn(t)?100:1):NaN;return Ms(0,Vn(s,e),1)}const Hn=/\s*;\s*/,Nn=/\s*:\s*/;function Un(t,e){let s,i;const r=t.getAttribute("style");if(r){const t=r.split(Hn);""===t[t.length-1]&&t.pop();for(let e=t.length;e--;){const[r,n]=t[e].split(Nn).map((t=>t.trim()));"stop-color"===r?s=n:"stop-opacity"===r&&(i=n)}}const n=new Ie(s||t.getAttribute("stop-color")||"rgb(0,0,0)");return{offset:Gn(t.getAttribute("offset"),0),color:n.toRgb(),opacity:Vn(parseFloat(i||t.getAttribute("stop-opacity")||""),1)*n.getAlpha()*e}}function qn(t,e){const s=[],i=t.getElementsByTagName("stop"),r=Gn(e,1);for(let t=i.length;t--;)s.push(Un(i[t],r));return s}function Kn(t){return"linearGradient"===t.nodeName||"LINEARGRADIENT"===t.nodeName?"linear":"radial"}function Jn(t){return"userSpaceOnUse"===t.getAttribute("gradientUnits")?"pixels":"percentage"}function Qn(t,e){return t.getAttribute(e)}function Zn(t,e){return function(t,e){let s,{width:i,height:r,gradientUnits:n}=e;return Object.entries(t).reduce(((t,e)=>{let[o,a]=e;if("Infinity"===a)s=1;else if("-Infinity"===a)s=0;else{const t="string"==typeof a;s=t?parseFloat(a):a,t&&zn(a)&&(s*=.01,"pixels"===n&&("x1"!==o&&"x2"!==o&&"r2"!==o||(s*=i),"y1"!==o&&"y2"!==o||(s*=r)))}return t[o]=s,t}),{})}("linear"===Kn(t)?function(t){return{x1:Qn(t,"x1")||0,y1:Qn(t,"y1")||0,x2:Qn(t,"x2")||"100%",y2:Qn(t,"y2")||0}}(t):function(t){return{x1:Qn(t,"fx")||Qn(t,"cx")||"50%",y1:Qn(t,"fy")||Qn(t,"cy")||"50%",r1:0,x2:Qn(t,"cx")||"50%",y2:Qn(t,"cy")||"50%",r2:Qn(t,"r")||"50%"}}(t),s(s({},e),{},{gradientUnits:Jn(t)}))}class $n{constructor(t){const{type:e="linear",gradientUnits:i="pixels",coords:r={},colorStops:n=[],offsetX:o=0,offsetY:a=0,gradientTransform:h,id:c}=t||{};Object.assign(this,{type:e,gradientUnits:i,coords:s(s({},"radial"===e?Wn:Yn),r),colorStops:n,offsetX:o,offsetY:a,gradientTransform:h,id:c?"".concat(c,"_").concat(ft()):ft()})}addColorStop(t){for(const e in t){const s=new Ie(t[e]);this.colorStops.push({offset:parseFloat(e),color:s.toRgb(),opacity:s.getAlpha()})}return this}toObject(t){return s(s({},Yt(this,t)),{},{type:this.type,coords:s({},this.coords),colorStops:this.colorStops.map((t=>s({},t))),offsetX:this.offsetX,offsetY:this.offsetY,gradientUnits:this.gradientUnits,gradientTransform:this.gradientTransform?[...this.gradientTransform]:void 0})}toSVG(t){let{additionalTransform:e}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const i=[],r=this.gradientTransform?this.gradientTransform.concat():T.concat(),n="pixels"===this.gradientUnits?"userSpaceOnUse":"objectBoundingBox",o=this.colorStops.map((t=>s({},t))).sort(((t,e)=>t.offset-e.offset));let a=-this.offsetX,h=-this.offsetY;var c;"objectBoundingBox"===n?(a/=t.width,h/=t.height):(a+=t.width/2,h+=t.height/2),(c=t)&&"function"==typeof c._renderPathCommands&&"percentage"!==this.gradientUnits&&(a-=t.pathOffset.x,h-=t.pathOffset.y),r[4]-=a,r[5]-=h;const l=['id="SVGID_'.concat(this.id,'"'),'gradientUnits="'.concat(n,'"'),'gradientTransform="'.concat(e?e+" ":"").concat(zt(r),'"'),""].join(" ");if("linear"===this.type){const{x1:t,y1:e,x2:s,y2:r}=this.coords;i.push("<linearGradient ",l,' x1="',t,'" y1="',e,'" x2="',s,'" y2="',r,'">\n')}else if("radial"===this.type){const{x1:t,y1:e,x2:s,y2:r,r1:n,r2:a}=this.coords,h=n>a;i.push("<radialGradient ",l,' cx="',h?t:s,'" cy="',h?e:r,'" r="',h?n:a,'" fx="',h?s:t,'" fy="',h?r:e,'">\n'),h&&(o.reverse(),o.forEach((t=>{t.offset=1-t.offset})));const c=Math.min(n,a);if(c>0){const t=c/Math.max(n,a);o.forEach((e=>{e.offset+=t*(1-e.offset)}))}}return o.forEach((t=>{let{color:e,offset:s,opacity:r}=t;i.push("<stop ",'offset="',100*s+"%",'" style="stop-color:',e,void 0!==r?";stop-opacity: "+r:";",'"/>\n')})),i.push("linear"===this.type?"</linearGradient>":"</radialGradient>","\n"),i.join("")}toLive(t){const{x1:e,y1:s,x2:i,y2:r,r1:n,r2:o}=this.coords,a="linear"===this.type?t.createLinearGradient(e,s,i,r):t.createRadialGradient(e,s,n,i,r,o);return this.colorStops.forEach((t=>{let{color:e,opacity:s,offset:i}=t;a.addColorStop(i,void 0!==s?new Ie(e).setAlpha(s).toRgba():e)})),a}static async fromObject(t){const{colorStops:e,gradientTransform:i}=t;return new this(s(s({},t),{},{colorStops:e?e.map((t=>s({},t))):void 0,gradientTransform:i?[...i]:void 0}))}static fromElement(t,e,i){const r=Jn(t),n=e._findCenterFromElement();return new this(s({id:t.getAttribute("id")||void 0,type:Kn(t),coords:Zn(t,{width:i.viewBoxWidth||i.width,height:i.viewBoxHeight||i.height}),colorStops:qn(t,i.opacity),gradientUnits:r,gradientTransform:wr(t.getAttribute("gradientTransform")||"")},"pixels"===r?{offsetX:e.width/2-n.x,offsetY:e.height/2-n.y}:{offsetX:0,offsetY:0}))}}t($n,"type","Gradient"),tt.setClass($n,"gradient"),tt.setClass($n,"linear"),tt.setClass($n,"radial");const to=["type","source","patternTransform"];class eo{get type(){return"pattern"}set type(t){a("warn","Setting type has no effect",t)}constructor(e){t(this,"repeat","repeat"),t(this,"offsetX",0),t(this,"offsetY",0),t(this,"crossOrigin",""),this.id=ft(),Object.assign(this,e)}isImageSource(){return!!this.source&&"string"==typeof this.source.src}isCanvasSource(){return!!this.source&&!!this.source.toDataURL}sourceToString(){return this.isImageSource()?this.source.src:this.isCanvasSource()?this.source.toDataURL():""}toLive(t){return this.source&&(!this.isImageSource()||this.source.complete&&0!==this.source.naturalWidth&&0!==this.source.naturalHeight)?t.createPattern(this.source,this.repeat):null}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];const{repeat:e,crossOrigin:i}=this;return s(s({},Yt(this,t)),{},{type:"pattern",source:this.sourceToString(),repeat:e,crossOrigin:i,offsetX:Vt(this.offsetX,o.NUM_FRACTION_DIGITS),offsetY:Vt(this.offsetY,o.NUM_FRACTION_DIGITS),patternTransform:this.patternTransform?[...this.patternTransform]:null})}toSVG(t){let{width:e,height:s}=t;const{source:i,repeat:r,id:n}=this,o=Vn(this.offsetX/e,0),a=Vn(this.offsetY/s,0),h="repeat-y"===r||"no-repeat"===r?1+Math.abs(o||0):Vn(i.width/e,0),c="repeat-x"===r||"no-repeat"===r?1+Math.abs(a||0):Vn(i.height/s,0);return['<pattern id="SVGID_'.concat(n,'" x="').concat(o,'" y="').concat(a,'" width="').concat(h,'" height="').concat(c,'">'),'<image x="0" y="0" width="'.concat(i.width,'" height="').concat(i.height,'" xlink:href="').concat(this.sourceToString(),'"></image>'),"</pattern>",""].join("\n")}static async fromObject(t,e){let{type:r,source:n,patternTransform:o}=t,a=i(t,to);const h=await It(n,s(s({},e),{},{crossOrigin:a.crossOrigin}));return new this(s(s({},a),{},{patternTransform:o&&o.slice(0),source:h}))}}t(eo,"type","Pattern"),tt.setClass(eo),tt.setClass(eo,"pattern");class so{constructor(e){t(this,"color","rgb(0, 0, 0)"),t(this,"width",1),t(this,"shadow",null),t(this,"strokeLineCap","round"),t(this,"strokeLineJoin","round"),t(this,"strokeMiterLimit",10),t(this,"strokeDashArray",null),t(this,"limitedToCanvasSize",!1),this.canvas=e}_setBrushStyles(t){t.strokeStyle=this.color,t.lineWidth=this.width,t.lineCap=this.strokeLineCap,t.miterLimit=this.strokeMiterLimit,t.lineJoin=this.strokeLineJoin,t.setLineDash(this.strokeDashArray||[])}_saveAndTransform(t){const e=this.canvas.viewportTransform;t.save(),t.transform(e[0],e[1],e[2],e[3],e[4],e[5])}needsFullRender(){return new Ie(this.color).getAlpha()<1||!!this.shadow}_setShadow(){if(!this.shadow||!this.canvas)return;const t=this.canvas,e=this.shadow,s=t.contextTop,i=t.getZoom()*t.getRetinaScaling();s.shadowColor=e.color,s.shadowBlur=e.blur*i,s.shadowOffsetX=e.offsetX*i,s.shadowOffsetY=e.offsetY*i}_resetShadow(){const t=this.canvas.contextTop;t.shadowColor="",t.shadowBlur=t.shadowOffsetX=t.shadowOffsetY=0}_isOutSideCanvas(t){return t.x<0||t.x>this.canvas.getWidth()||t.y<0||t.y>this.canvas.getHeight()}}const io=["path","left","top"],ro=["d"];class no extends Li{constructor(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},{path:s,left:r,top:n}=e,o=i(e,io);super(),Object.assign(this,no.ownDefaults),this.setOptions(o),this._setPath(t||[],!0),"number"==typeof r&&this.set(M,r),"number"==typeof n&&this.set(P,n)}_setPath(t,e){this.path=nn(Array.isArray(t)?t:bn(t)),this.setBoundingBox(e)}_findCenterFromElement(){const t=this._calcBoundsFromPath();return new ot(t.left+t.width/2,t.top+t.height/2)}_renderPathCommands(t){const e=-this.pathOffset.x,s=-this.pathOffset.y;t.beginPath();for(const i of this.path)switch(i[0]){case"L":t.lineTo(i[1]+e,i[2]+s);break;case"M":t.moveTo(i[1]+e,i[2]+s);break;case"C":t.bezierCurveTo(i[1]+e,i[2]+s,i[3]+e,i[4]+s,i[5]+e,i[6]+s);break;case"Q":t.quadraticCurveTo(i[1]+e,i[2]+s,i[3]+e,i[4]+s);break;case"Z":t.closePath()}}_render(t){this._renderPathCommands(t),this._renderPaintInOrder(t)}toString(){return"#<Path (".concat(this.complexity(),'): { "top": ').concat(this.top,', "left": ').concat(this.left," }>")}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return s(s({},super.toObject(t)),{},{path:this.path.map((t=>t.slice()))})}toDatalessObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];const e=this.toObject(t);return this.sourcePath&&(delete e.path,e.sourcePath=this.sourcePath),e}_toSVG(){const t=wn(this.path,o.NUM_FRACTION_DIGITS);return["<path ","COMMON_PARTS",'d="'.concat(t,'" stroke-linecap="round" />\n')]}_getOffsetTransform(){const t=o.NUM_FRACTION_DIGITS;return" translate(".concat(Vt(-this.pathOffset.x,t),", ").concat(Vt(-this.pathOffset.y,t),")")}toClipPathSVG(t){const e=this._getOffsetTransform();return"\t"+this._createBaseClipPathSVGMarkup(this._toSVG(),{reviver:t,additionalTransform:e})}toSVG(t){const e=this._getOffsetTransform();return this._createBaseSVGMarkup(this._toSVG(),{reviver:t,additionalTransform:e})}complexity(){return this.path.length}setDimensions(){this.setBoundingBox()}setBoundingBox(t){const{width:e,height:s,pathOffset:i}=this._calcDimensions();this.set({width:e,height:s,pathOffset:i}),t&&this.setPositionByOrigin(i,D,D)}_calcBoundsFromPath(){const t=[];let e=0,s=0,i=0,r=0;for(const n of this.path)switch(n[0]){case"L":i=n[1],r=n[2],t.push({x:e,y:s},{x:i,y:r});break;case"M":i=n[1],r=n[2],e=i,s=r;break;case"C":t.push(...sn(i,r,n[1],n[2],n[3],n[4],n[5],n[6])),i=n[5],r=n[6];break;case"Q":t.push(...sn(i,r,n[1],n[2],n[1],n[2],n[3],n[4])),i=n[3],r=n[4];break;case"Z":i=e,r=s}return he(t)}_calcDimensions(){const t=this._calcBoundsFromPath();return s(s({},t),{},{pathOffset:new ot(t.left+t.width/2,t.top+t.height/2)})}static fromObject(t){return this._fromObject(t,{extraParam:"path"})}static async fromElement(t,e,r){const n=Pr(t,this.ATTRIBUTE_NAMES,r),{d:o}=n;return new this(o,s(s(s({},i(n,ro)),e),{},{left:void 0,top:void 0}))}}t(no,"type","Path"),t(no,"cacheProperties",[...Es,"path","fillRule"]),t(no,"ATTRIBUTE_NAMES",[...Zi,"d"]),tt.setClass(no),tt.setSVGClass(no);class oo extends so{constructor(e){super(e),t(this,"decimate",.4),t(this,"drawStraightLine",!1),t(this,"straightLineKey","shiftKey"),this._points=[],this._hasStraightLine=!1}needsFullRender(){return super.needsFullRender()||this._hasStraightLine}static drawSegment(t,e,s){const i=e.midPointFrom(s);return t.quadraticCurveTo(e.x,e.y,i.x,i.y),i}onMouseDown(t,e){let{e:s}=e;this.canvas._isMainEvent(s)&&(this.drawStraightLine=!!this.straightLineKey&&s[this.straightLineKey],this._prepareForDrawing(t),this._addPoint(t),this._render())}onMouseMove(t,e){let{e:s}=e;if(this.canvas._isMainEvent(s)&&(this.drawStraightLine=!!this.straightLineKey&&s[this.straightLineKey],(!0!==this.limitedToCanvasSize||!this._isOutSideCanvas(t))&&this._addPoint(t)&&this._points.length>1))if(this.needsFullRender())this.canvas.clearContext(this.canvas.contextTop),this._render();else{const t=this._points,e=t.length,s=this.canvas.contextTop;this._saveAndTransform(s),this.oldEnd&&(s.beginPath(),s.moveTo(this.oldEnd.x,this.oldEnd.y)),this.oldEnd=oo.drawSegment(s,t[e-2],t[e-1]),s.stroke(),s.restore()}}onMouseUp(t){let{e:e}=t;return!this.canvas._isMainEvent(e)||(this.drawStraightLine=!1,this.oldEnd=void 0,this._finalizeAndAddPath(),!1)}_prepareForDrawing(t){this._reset(),this._addPoint(t),this.canvas.contextTop.moveTo(t.x,t.y)}_addPoint(t){return!(this._points.length>1&&t.eq(this._points[this._points.length-1]))&&(this.drawStraightLine&&this._points.length>1&&(this._hasStraightLine=!0,this._points.pop()),this._points.push(t),!0)}_reset(){this._points=[],this._setBrushStyles(this.canvas.contextTop),this._setShadow(),this._hasStraightLine=!1}_render(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:this.canvas.contextTop,e=this._points[0],s=this._points[1];if(this._saveAndTransform(t),t.beginPath(),2===this._points.length&&e.x===s.x&&e.y===s.y){const t=this.width/1e3;e.x-=t,s.x+=t}t.moveTo(e.x,e.y);for(let i=1;i<this._points.length;i++)oo.drawSegment(t,e,s),e=this._points[i],s=this._points[i+1];t.lineTo(e.x,e.y),t.stroke(),t.restore()}convertPointsToSVGPath(t){const e=this.width/1e3;return Sn(t,e)}createPath(t){const e=new no(t,{fill:null,stroke:this.color,strokeWidth:this.width,strokeLineCap:this.strokeLineCap,strokeMiterLimit:this.strokeMiterLimit,strokeLineJoin:this.strokeLineJoin,strokeDashArray:this.strokeDashArray});return this.shadow&&(this.shadow.affectStroke=!0,e.shadow=new Ds(this.shadow)),e}decimatePoints(t,e){if(t.length<=2)return t;let s,i=t[0];const r=this.canvas.getZoom(),n=Math.pow(e/r,2),o=t.length-1,a=[i];for(let e=1;e<o-1;e++)s=Math.pow(i.x-t[e].x,2)+Math.pow(i.y-t[e].y,2),s>=n&&(i=t[e],a.push(i));return a.push(t[o]),a}_finalizeAndAddPath(){this.canvas.contextTop.closePath(),this.decimate&&(this._points=this.decimatePoints(this._points,this.decimate));const t=this.convertPointsToSVGPath(this._points);if(function(t){return"M 0 0 Q 0 0 0 0 L 0 0"===wn(t)}(t))return void this.canvas.requestRenderAll();const e=this.createPath(t);this.canvas.clearContext(this.canvas.contextTop),this.canvas.fire("before:path:created",{path:e}),this.canvas.add(e),this.canvas.requestRenderAll(),e.setCoords(),this._resetShadow(),this.canvas.fire("path:created",{path:e})}}const ao=["left","top","radius"],ho=["radius","startAngle","endAngle","counterClockwise"];class co extends Li{static getDefaults(){return s(s({},super.getDefaults()),co.ownDefaults)}constructor(t){super(),Object.assign(this,co.ownDefaults),this.setOptions(t)}_set(t,e){return super._set(t,e),"radius"===t&&this.setRadius(e),this}_render(t){t.beginPath(),t.arc(0,0,this.radius,xt(this.startAngle),xt(this.endAngle),this.counterClockwise),this._renderPaintInOrder(t)}getRadiusX(){return this.get("radius")*this.get(H)}getRadiusY(){return this.get("radius")*this.get(N)}setRadius(t){this.radius=t,this.set({width:2*t,height:2*t})}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return super.toObject([...ho,...t])}_toSVG(){const t=(this.endAngle-this.startAngle)%360;if(0===t)return["<circle ","COMMON_PARTS",'cx="0" cy="0" ','r="',"".concat(this.radius),'" />\n'];{const{radius:e}=this,s=xt(this.startAngle),i=xt(this.endAngle),r=rt(s)*e,n=nt(s)*e,o=rt(i)*e,a=nt(i)*e,h=t>180?1:0,c=this.counterClockwise?0:1;return['<path d="M '.concat(r," ").concat(n," A ").concat(e," ").concat(e," 0 ").concat(h," ").concat(c," ").concat(o," ").concat(a,'" '),"COMMON_PARTS"," />\n"]}}static async fromElement(t,e,r){const n=Pr(t,this.ATTRIBUTE_NAMES,r),{left:o=0,top:a=0,radius:h=0}=n;return new this(s(s({},i(n,ao)),{},{radius:h,left:o-h,top:a-h}))}static fromObject(t){return super._fromObject(t)}}t(co,"type","Circle"),t(co,"cacheProperties",[...Es,...ho]),t(co,"ownDefaults",{radius:0,startAngle:0,endAngle:360,counterClockwise:!1}),t(co,"ATTRIBUTE_NAMES",["cx","cy","r",...Zi]),tt.setClass(co),tt.setSVGClass(co);class lo extends so{constructor(e){super(e),t(this,"width",10),this.points=[]}drawDot(t){const e=this.addPoint(t),s=this.canvas.contextTop;this._saveAndTransform(s),this.dot(s,e),s.restore()}dot(t,e){t.fillStyle=e.fill,t.beginPath(),t.arc(e.x,e.y,e.radius,0,2*Math.PI,!1),t.closePath(),t.fill()}onMouseDown(t){this.points=[],this.canvas.clearContext(this.canvas.contextTop),this._setShadow(),this.drawDot(t)}_render(){const t=this.canvas.contextTop,e=this.points;this._saveAndTransform(t);for(let s=0;s<e.length;s++)this.dot(t,e[s]);t.restore()}onMouseMove(t){!0===this.limitedToCanvasSize&&this._isOutSideCanvas(t)||(this.needsFullRender()?(this.canvas.clearContext(this.canvas.contextTop),this.addPoint(t),this._render()):this.drawDot(t))}onMouseUp(){const t=this.canvas.renderOnAddRemove;this.canvas.renderOnAddRemove=!1;const e=[];for(let t=0;t<this.points.length;t++){const s=this.points[t],i=new co({radius:s.radius,left:s.x,top:s.y,originX:D,originY:D,fill:s.fill});this.shadow&&(i.shadow=new Ds(this.shadow)),e.push(i)}const s=new Ur(e,{canvas:this.canvas});this.canvas.fire("before:path:created",{path:s}),this.canvas.add(s),this.canvas.fire("path:created",{path:s}),this.canvas.clearContext(this.canvas.contextTop),this._resetShadow(),this.canvas.renderOnAddRemove=t,this.canvas.requestRenderAll()}addPoint(t){let{x:e,y:s}=t;const i={x:e,y:s,radius:On(Math.max(0,this.width-20),this.width+20)/2,fill:new Ie(this.color).setAlpha(On(0,100)/100).toRgba()};return this.points.push(i),i}}class uo extends so{constructor(e){super(e),t(this,"width",10),t(this,"density",20),t(this,"dotWidth",1),t(this,"dotWidthVariance",1),t(this,"randomOpacity",!1),t(this,"optimizeOverlapping",!0),this.sprayChunks=[],this.sprayChunk=[]}onMouseDown(t){this.sprayChunks=[],this.canvas.clearContext(this.canvas.contextTop),this._setShadow(),this.addSprayChunk(t),this.renderChunck(this.sprayChunk)}onMouseMove(t){!0===this.limitedToCanvasSize&&this._isOutSideCanvas(t)||(this.addSprayChunk(t),this.renderChunck(this.sprayChunk))}onMouseUp(){const t=this.canvas.renderOnAddRemove;this.canvas.renderOnAddRemove=!1;const e=[];for(let t=0;t<this.sprayChunks.length;t++){const s=this.sprayChunks[t];for(let t=0;t<s.length;t++){const i=s[t],r=new jr({width:i.width,height:i.width,left:i.x+1,top:i.y+1,originX:D,originY:D,fill:this.color});e.push(r)}}const s=new Ur(this.optimizeOverlapping?function(t){const e={},s=[];for(let i,r=0;r<t.length;r++)i="".concat(t[r].left).concat(t[r].top),e[i]||(e[i]=!0,s.push(t[r]));return s}(e):e,{objectCaching:!0,subTargetCheck:!1,interactive:!1});this.shadow&&s.set("shadow",new Ds(this.shadow)),this.canvas.fire("before:path:created",{path:s}),this.canvas.add(s),this.canvas.fire("path:created",{path:s}),this.canvas.clearContext(this.canvas.contextTop),this._resetShadow(),this.canvas.renderOnAddRemove=t,this.canvas.requestRenderAll()}renderChunck(t){const e=this.canvas.contextTop;e.fillStyle=this.color,this._saveAndTransform(e);for(let s=0;s<t.length;s++){const i=t[s];e.globalAlpha=i.opacity,e.fillRect(i.x,i.y,i.width,i.width)}e.restore()}_render(){const t=this.canvas.contextTop;t.fillStyle=this.color,this._saveAndTransform(t);for(let t=0;t<this.sprayChunks.length;t++)this.renderChunck(this.sprayChunks[t]);t.restore()}addSprayChunk(t){this.sprayChunk=[];const e=this.width/2;for(let s=0;s<this.density;s++)this.sprayChunk.push({x:On(t.x-e,t.x+e),y:On(t.y-e,t.y+e),width:this.dotWidthVariance?On(Math.max(1,this.dotWidth-this.dotWidthVariance),this.dotWidth+this.dotWidthVariance):this.dotWidth,opacity:this.randomOpacity?On(0,100)/100:1});this.sprayChunks.push(this.sprayChunk)}}class go extends oo{constructor(t){super(t)}getPatternSrc(){const t=pt(),e=t.getContext("2d");return t.width=t.height=25,e&&(e.fillStyle=this.color,e.beginPath(),e.arc(10,10,10,0,2*Math.PI,!1),e.closePath(),e.fill()),t}getPattern(t){return t.createPattern(this.source||this.getPatternSrc(),"repeat")}_setBrushStyles(t){super._setBrushStyles(t);const e=this.getPattern(t);e&&(t.strokeStyle=e)}createPath(t){const e=super.createPath(t),s=e._getLeftTopCoords().scalarAdd(e.strokeWidth/2);return e.stroke=new eo({source:this.source||this.getPatternSrc(),offsetX:-s.x,offsetY:-s.y}),e}}const fo=["x1","y1","x2","y2"],po=["x1","y1","x2","y2"],mo=["x1","x2","y1","y2"];class vo extends Li{constructor(){let[t,e,s,i]=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[0,0,0,0],r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};super(),Object.assign(this,vo.ownDefaults),this.setOptions(r),this.x1=t,this.x2=s,this.y1=e,this.y2=i,this._setWidthHeight();const{left:n,top:o}=r;"number"==typeof n&&this.set(M,n),"number"==typeof o&&this.set(P,o)}_setWidthHeight(){const{x1:t,y1:e,x2:s,y2:i}=this;this.width=Math.abs(s-t),this.height=Math.abs(i-e);const{left:r,top:n,width:o,height:a}=he([{x:t,y:e},{x:s,y:i}]),h=new ot(r+o/2,n+a/2);this.setPositionByOrigin(h,D,D)}_set(t,e){return super._set(t,e),mo.includes(t)&&this._setWidthHeight(),this}_render(t){t.beginPath();const e=this.calcLinePoints();t.moveTo(e.x1,e.y1),t.lineTo(e.x2,e.y2),t.lineWidth=this.strokeWidth;const s=t.strokeStyle;var i;Gt(this.stroke)?t.strokeStyle=this.stroke.toLive(t):t.strokeStyle=null!==(i=this.stroke)&&void 0!==i?i:t.fillStyle;this.stroke&&this._renderStroke(t),t.strokeStyle=s}_findCenterFromElement(){return new ot((this.x1+this.x2)/2,(this.y1+this.y2)/2)}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return s(s({},super.toObject(t)),this.calcLinePoints())}_getNonTransformedDimensions(){const t=super._getNonTransformedDimensions();return"butt"===this.strokeLineCap&&(0===this.width&&(t.y-=this.strokeWidth),0===this.height&&(t.x-=this.strokeWidth)),t}calcLinePoints(){const{x1:t,x2:e,y1:s,y2:i,width:r,height:n}=this,o=t<=e?-1:1,a=s<=i?-1:1;return{x1:o*r/2,x2:o*-r/2,y1:a*n/2,y2:a*-n/2}}_toSVG(){const{x1:t,x2:e,y1:s,y2:i}=this.calcLinePoints();return["<line ","COMMON_PARTS",'x1="'.concat(t,'" y1="').concat(s,'" x2="').concat(e,'" y2="').concat(i,'" />\n')]}static async fromElement(t,e,s){const r=Pr(t,this.ATTRIBUTE_NAMES,s),{x1:n=0,y1:o=0,x2:a=0,y2:h=0}=r;return new this([n,o,a,h],i(r,fo))}static fromObject(t){let{x1:e,y1:r,x2:n,y2:o}=t,a=i(t,po);return this._fromObject(s(s({},a),{},{points:[e,r,n,o]}),{extraParam:"points"})}}t(vo,"type","Line"),t(vo,"cacheProperties",[...Es,...mo]),t(vo,"ATTRIBUTE_NAMES",Zi.concat(mo)),tt.setClass(vo),tt.setSVGClass(vo);class yo extends Li{static getDefaults(){return s(s({},super.getDefaults()),yo.ownDefaults)}constructor(t){super(),Object.assign(this,yo.ownDefaults),this.setOptions(t)}_render(t){const e=this.width/2,s=this.height/2;t.beginPath(),t.moveTo(-e,s),t.lineTo(0,-s),t.lineTo(e,s),t.closePath(),this._renderPaintInOrder(t)}_toSVG(){const t=this.width/2,e=this.height/2;return["<polygon ","COMMON_PARTS",'points="',"".concat(-t," ").concat(e,",0 ").concat(-e,",").concat(t," ").concat(e),'" />']}}t(yo,"type","Triangle"),t(yo,"ownDefaults",{width:100,height:100}),tt.setClass(yo),tt.setSVGClass(yo);const _o=["rx","ry"];class xo extends Li{static getDefaults(){return s(s({},super.getDefaults()),xo.ownDefaults)}constructor(t){super(),Object.assign(this,xo.ownDefaults),this.setOptions(t)}_set(t,e){switch(super._set(t,e),t){case"rx":this.rx=e,this.set("width",2*e);break;case"ry":this.ry=e,this.set("height",2*e)}return this}getRx(){return this.get("rx")*this.get(H)}getRy(){return this.get("ry")*this.get(N)}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return super.toObject([..._o,...t])}_toSVG(){return["<ellipse ","COMMON_PARTS",'cx="0" cy="0" rx="'.concat(this.rx,'" ry="').concat(this.ry,'" />\n')]}_render(t){t.beginPath(),t.save(),t.transform(1,0,0,this.ry/this.rx,0,0),t.arc(0,0,this.rx,0,S,!1),t.restore(),this._renderPaintInOrder(t)}static async fromElement(t,e,s){const i=Pr(t,this.ATTRIBUTE_NAMES,s);return i.left=(i.left||0)-i.rx,i.top=(i.top||0)-i.ry,new this(i)}}function Co(t){if(!t)return[];const e=t.replace(/,/g," ").trim().split(/\s+/),s=[];for(let t=0;t<e.length;t+=2)s.push({x:parseFloat(e[t]),y:parseFloat(e[t+1])});return s}t(xo,"type","Ellipse"),t(xo,"cacheProperties",[...Es,..._o]),t(xo,"ownDefaults",{rx:0,ry:0}),t(xo,"ATTRIBUTE_NAMES",[...Zi,"cx","cy","rx","ry"]),tt.setClass(xo),tt.setSVGClass(xo);const bo=["left","top"],So={exactBoundingBox:!1};class wo extends Li{static getDefaults(){return s(s({},super.getDefaults()),wo.ownDefaults)}constructor(){let e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[],s=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};super(),t(this,"strokeDiff",void 0),Object.assign(this,wo.ownDefaults),this.setOptions(s),this.points=e;const{left:i,top:r}=s;this.initialized=!0,this.setBoundingBox(!0),"number"==typeof i&&this.set(M,i),"number"==typeof r&&this.set(P,r)}isOpen(){return!0}_projectStrokeOnPoints(t){return Wi(this.points,t,this.isOpen())}_calcDimensions(t){t=s({scaleX:this.scaleX,scaleY:this.scaleY,skewX:this.skewX,skewY:this.skewY,strokeLineCap:this.strokeLineCap,strokeLineJoin:this.strokeLineJoin,strokeMiterLimit:this.strokeMiterLimit,strokeUniform:this.strokeUniform,strokeWidth:this.strokeWidth},t||{});const e=this.exactBoundingBox?this._projectStrokeOnPoints(t).map((t=>t.projectedPoint)):this.points;if(0===e.length)return{left:0,top:0,width:0,height:0,pathOffset:new ot,strokeOffset:new ot,strokeDiff:new ot};const i=he(e),r=Lt(s(s({},t),{},{scaleX:1,scaleY:1})),n=he(this.points.map((t=>St(t,r,!0)))),o=new ot(this.scaleX,this.scaleY);let a=i.left+i.width/2,h=i.top+i.height/2;return this.exactBoundingBox&&(a-=h*Math.tan(xt(this.skewX)),h-=a*Math.tan(xt(this.skewY))),s(s({},i),{},{pathOffset:new ot(a,h),strokeOffset:new ot(n.left,n.top).subtract(new ot(i.left,i.top)).multiply(o),strokeDiff:new ot(i.width,i.height).subtract(new ot(n.width,n.height)).multiply(o)})}_findCenterFromElement(){const t=he(this.points);return new ot(t.left+t.width/2,t.top+t.height/2)}setDimensions(){this.setBoundingBox()}setBoundingBox(t){const{left:e,top:s,width:i,height:r,pathOffset:n,strokeOffset:o,strokeDiff:a}=this._calcDimensions();this.set({width:i,height:r,pathOffset:n,strokeOffset:o,strokeDiff:a}),t&&this.setPositionByOrigin(new ot(e+i/2,s+r/2),D,D)}isStrokeAccountedForInDimensions(){return this.exactBoundingBox}_getNonTransformedDimensions(){return this.exactBoundingBox?new ot(this.width,this.height):super._getNonTransformedDimensions()}_getTransformedDimensions(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};if(this.exactBoundingBox){let n;if(Object.keys(t).some((t=>this.strokeUniform||this.constructor.layoutProperties.includes(t)))){var e,s;const{width:i,height:r}=this._calcDimensions(t);n=new ot(null!==(e=t.width)&&void 0!==e?e:i,null!==(s=t.height)&&void 0!==s?s:r)}else{var i,r;n=new ot(null!==(i=t.width)&&void 0!==i?i:this.width,null!==(r=t.height)&&void 0!==r?r:this.height)}return n.multiply(new ot(t.scaleX||this.scaleX,t.scaleY||this.scaleY))}return super._getTransformedDimensions(t)}_set(t,e){const s=this.initialized&&this[t]!==e,i=super._set(t,e);return this.exactBoundingBox&&s&&((t===H||t===N)&&this.strokeUniform&&this.constructor.layoutProperties.includes("strokeUniform")||this.constructor.layoutProperties.includes(t))&&this.setDimensions(),i}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return s(s({},super.toObject(t)),{},{points:this.points.map((t=>{let{x:e,y:s}=t;return{x:e,y:s}}))})}_toSVG(){const t=[],e=this.pathOffset.x,s=this.pathOffset.y,i=o.NUM_FRACTION_DIGITS;for(let r=0,n=this.points.length;r<n;r++)t.push(Vt(this.points[r].x-e,i),",",Vt(this.points[r].y-s,i)," ");return["<".concat(this.constructor.type.toLowerCase()," "),"COMMON_PARTS",'points="'.concat(t.join(""),'" />\n')]}_render(t){const e=this.points.length,s=this.pathOffset.x,i=this.pathOffset.y;if(e&&!isNaN(this.points[e-1].y)){t.beginPath(),t.moveTo(this.points[0].x-s,this.points[0].y-i);for(let r=0;r<e;r++){const e=this.points[r];t.lineTo(e.x-s,e.y-i)}!this.isOpen()&&t.closePath(),this._renderPaintInOrder(t)}}complexity(){return this.points.length}static async fromElement(t,e,r){return new this(Co(t.getAttribute("points")),s(s({},i(Pr(t,this.ATTRIBUTE_NAMES,r),bo)),e))}static fromObject(t){return this._fromObject(t,{extraParam:"points"})}}t(wo,"ownDefaults",So),t(wo,"type","Polyline"),t(wo,"layoutProperties",[U,q,"strokeLineCap","strokeLineJoin","strokeMiterLimit","strokeWidth","strokeUniform","points"]),t(wo,"cacheProperties",[...Es,"points"]),t(wo,"ATTRIBUTE_NAMES",[...Zi]),tt.setClass(wo),tt.setSVGClass(wo);class To extends wo{isOpen(){return!1}}t(To,"ownDefaults",So),t(To,"type","Polygon"),tt.setClass(To),tt.setSVGClass(To);class Oo extends Li{isEmptyStyles(t){if(!this.styles)return!0;if(void 0!==t&&!this.styles[t])return!0;const e=void 0===t?this.styles:{line:this.styles[t]};for(const t in e)for(const s in e[t])for(const i in e[t][s])return!1;return!0}styleHas(t,e){if(!this.styles)return!1;if(void 0!==e&&!this.styles[e])return!1;const s=void 0===e?this.styles:{0:this.styles[e]};for(const e in s)for(const i in s[e])if(void 0!==s[e][i][t])return!0;return!1}cleanStyle(t){if(!this.styles)return!1;const e=this.styles;let s,i,r=0,n=!0,o=0;for(const o in e){s=0;for(const a in e[o]){const h=e[o][a]||{};r++,void 0!==h[t]?(i?h[t]!==i&&(n=!1):i=h[t],h[t]===this[t]&&delete h[t]):n=!1,0!==Object.keys(h).length?s++:delete e[o][a]}0===s&&delete e[o]}for(let t=0;t<this._textLines.length;t++)o+=this._textLines[t].length;n&&r===o&&(this[t]=i,this.removeStyle(t))}removeStyle(t){if(!this.styles)return;const e=this.styles;let s,i,r;for(i in e){for(r in s=e[i],s)delete s[r][t],0===Object.keys(s[r]).length&&delete s[r];0===Object.keys(s).length&&delete e[i]}}_extendStyles(t,e){const{lineIndex:i,charIndex:r}=this.get2DCursorLocation(t);this._getLineStyle(i)||this._setLineStyle(i);const n=Wt(s(s({},this._getStyleDeclaration(i,r)),e),(t=>void 0!==t));this._setStyleDeclaration(i,r,n)}getSelectionStyles(t,e,s){const i=[];for(let r=t;r<(e||t);r++)i.push(this.getStyleAtPosition(r,s));return i}getStyleAtPosition(t,e){const{lineIndex:s,charIndex:i}=this.get2DCursorLocation(t);return e?this.getCompleteStyleDeclaration(s,i):this._getStyleDeclaration(s,i)}setSelectionStyles(t,e,s){for(let i=e;i<(s||e);i++)this._extendStyles(i,t);this._forceClearCache=!0}_getStyleDeclaration(t,e){var s;const i=this.styles&&this.styles[t];return i&&null!==(s=i[e])&&void 0!==s?s:{}}getCompleteStyleDeclaration(t,e){return s(s({},Yt(this,this.constructor._styleProperties)),this._getStyleDeclaration(t,e))}_setStyleDeclaration(t,e,s){this.styles[t][e]=s}_deleteStyleDeclaration(t,e){delete this.styles[t][e]}_getLineStyle(t){return!!this.styles[t]}_setLineStyle(t){this.styles[t]={}}_deleteLineStyle(t){delete this.styles[t]}}t(Oo,"_styleProperties",qe);const ko=/  +/g,Do=/"/g;function Mo(t,e,s,i,r){return"\t\t".concat(function(t,e){let{left:s,top:i,width:r,height:n}=e,a=arguments.length>2&&void 0!==arguments[2]?arguments[2]:o.NUM_FRACTION_DIGITS;const h=Ye(K,t,!1),[c,l,u,d]=[s,i,r,n].map((t=>Vt(t,a)));return"<rect ".concat(h,' x="').concat(c,'" y="').concat(l,'" width="').concat(u,'" height="').concat(d,'"></rect>')}(t,{left:e,top:s,width:i,height:r}),"\n")}const Po=["textAnchor","textDecoration","dx","dy","top","left","fontSize","strokeWidth"];let Eo;class Ao extends Oo{static getDefaults(){return s(s({},super.getDefaults()),Ao.ownDefaults)}constructor(e,s){super(),t(this,"__charBounds",[]),Object.assign(this,Ao.ownDefaults),this.setOptions(s),this.styles||(this.styles={}),this.text=e,this.initialized=!0,this.path&&this.setPathInfo(),this.initDimensions(),this.setCoords()}setPathInfo(){const t=this.path;t&&(t.segmentsInfo=mn(t.path))}_splitText(){const t=this._splitTextIntoLines(this.text);return this.textLines=t.lines,this._textLines=t.graphemeLines,this._unwrappedTextLines=t._unwrappedLines,this._text=t.graphemeText,t}initDimensions(){this._splitText(),this._clearCache(),this.dirty=!0,this.path?(this.width=this.path.width,this.height=this.path.height):(this.width=this.calcTextWidth()||this.cursorWidth||this.MIN_TEXT_WIDTH,this.height=this.calcTextHeight()),this.textAlign.includes(Je)&&this.enlargeSpaces()}enlargeSpaces(){let t,e,s,i,r,n,o;for(let a=0,h=this._textLines.length;a<h;a++)if((this.textAlign===Je||a!==h-1&&!this.isEndOfWrapping(a))&&(i=0,r=this._textLines[a],e=this.getLineWidth(a),e<this.width&&(o=this.textLines[a].match(this._reSpacesAndTabs)))){s=o.length,t=(this.width-e)/s;for(let e=0;e<=r.length;e++)n=this.__charBounds[a][e],this._reSpaceAndTab.test(r[e])?(n.width+=t,n.kernedWidth+=t,n.left+=i,i+=t):n.left+=i}}isEndOfWrapping(t){return t===this._textLines.length-1}missingNewlineOffset(t){return 1}get2DCursorLocation(t,e){const s=e?this._unwrappedTextLines:this._textLines;let i;for(i=0;i<s.length;i++){if(t<=s[i].length)return{lineIndex:i,charIndex:t};t-=s[i].length+this.missingNewlineOffset(i,e)}return{lineIndex:i-1,charIndex:s[i-1].length<t?s[i-1].length:t}}toString(){return"#<Text (".concat(this.complexity(),'): { "text": "').concat(this.text,'", "fontFamily": "').concat(this.fontFamily,'" }>')}_getCacheCanvasDimensions(){const t=super._getCacheCanvasDimensions(),e=this.fontSize;return t.width+=e*t.zoomX,t.height+=e*t.zoomY,t}_render(t){const e=this.path;e&&!e.isNotVisible()&&e._render(t),this._setTextStyles(t),this._renderTextLinesBackground(t),this._renderTextDecoration(t,"underline"),this._renderText(t),this._renderTextDecoration(t,"overline"),this._renderTextDecoration(t,"linethrough")}_renderText(t){this.paintFirst===J?(this._renderTextStroke(t),this._renderTextFill(t)):(this._renderTextFill(t),this._renderTextStroke(t))}_setTextStyles(t,e,s){if(t.textBaseline="alphabetic",this.path)switch(this.pathAlign){case D:t.textBaseline="middle";break;case"ascender":t.textBaseline=P;break;case"descender":t.textBaseline=E}t.font=this._getFontDeclaration(e,s)}calcTextWidth(){let t=this.getLineWidth(0);for(let e=1,s=this._textLines.length;e<s;e++){const s=this.getLineWidth(e);s>t&&(t=s)}return t}_renderTextLine(t,e,s,i,r,n){this._renderChars(t,e,s,i,r,n)}_renderTextLinesBackground(t){if(!this.textBackgroundColor&&!this.styleHas("textBackgroundColor"))return;const e=t.fillStyle,s=this._getLeftOffset();let i=this._getTopOffset();for(let e=0,r=this._textLines.length;e<r;e++){const r=this.getHeightOfLine(e);if(!this.textBackgroundColor&&!this.styleHas("textBackgroundColor",e)){i+=r;continue}const n=this._textLines[e].length,o=this._getLineLeftOffset(e);let a,h,c=0,l=0,u=this.getValueOfPropertyAt(e,0,"textBackgroundColor");const d=this.getHeightOfLineImpl(e);for(let r=0;r<n;r++){const n=this.__charBounds[e][r];h=this.getValueOfPropertyAt(e,r,"textBackgroundColor"),this.path?(t.save(),t.translate(n.renderLeft,n.renderTop),t.rotate(n.angle),t.fillStyle=h,h&&t.fillRect(-n.width/2,-d*(1-this._fontSizeFraction),n.width,d),t.restore()):h!==u?(a=s+o+l,"rtl"===this.direction&&(a=this.width-a-c),t.fillStyle=u,u&&t.fillRect(a,i,c,d),l=n.left,c=n.width,u=h):c+=n.kernedWidth}h&&!this.path&&(a=s+o+l,"rtl"===this.direction&&(a=this.width-a-c),t.fillStyle=h,t.fillRect(a,i,c,d)),i+=r}t.fillStyle=e,this._removeShadow(t)}_measureChar(t,e,s,i){const r=_.getFontCache(e),n=this._getFontDeclaration(e),o=s?s+t:t,a=s&&n===this._getFontDeclaration(i),h=e.fontSize/this.CACHE_FONT_SIZE;let c,l,u,d;if(s&&r.has(s)&&(u=r.get(s)),r.has(t)&&(d=c=r.get(t)),a&&r.has(o)&&(l=r.get(o),d=l-u),void 0===c||void 0===u||void 0===l){const i=function(){if(!Eo){const t=vt({width:0,height:0});Eo=t.getContext("2d")}return Eo}();this._setTextStyles(i,e,!0),void 0===c&&(d=c=i.measureText(t).width,r.set(t,c)),void 0===u&&a&&s&&(u=i.measureText(s).width,r.set(s,u)),a&&void 0===l&&(l=i.measureText(o).width,r.set(o,l),d=l-u)}return{width:c*h,kernedWidth:d*h}}getHeightOfChar(t,e){return this.getValueOfPropertyAt(t,e,"fontSize")}measureLine(t){const e=this._measureLine(t);return 0!==this.charSpacing&&(e.width-=this._getWidthOfCharSpacing()),e.width<0&&(e.width=0),e}_measureLine(t){let e,s,i=0;const r=this.pathSide===A,n=this.path,o=this._textLines[t],a=o.length,h=new Array(a);this.__charBounds[t]=h;for(let r=0;r<a;r++){const n=o[r];s=this._getGraphemeBox(n,t,r,e),h[r]=s,i+=s.kernedWidth,e=n}if(h[a]={left:s?s.left+s.width:0,width:0,kernedWidth:0,height:this.fontSize,deltaY:0},n&&n.segmentsInfo){let t=0;const e=n.segmentsInfo[n.segmentsInfo.length-1].length;switch(this.textAlign){case M:t=r?e-i:0;break;case D:t=(e-i)/2;break;case A:t=r?0:e-i}t+=this.pathStartOffset*(r?-1:1);for(let i=r?a-1:0;r?i>=0:i<a;r?i--:i++)s=h[i],t>e?t%=e:t<0&&(t+=e),this._setGraphemeOnPath(t,s),t+=s.kernedWidth}return{width:i,numOfSpaces:0}}_setGraphemeOnPath(t,e){const s=t+e.kernedWidth/2,i=this.path,r=vn(i.path,s,i.segmentsInfo);e.renderLeft=r.x-i.pathOffset.x,e.renderTop=r.y-i.pathOffset.y,e.angle=r.angle+(this.pathSide===A?Math.PI:0)}_getGraphemeBox(t,e,s,i,r){const n=this.getCompleteStyleDeclaration(e,s),o=i?this.getCompleteStyleDeclaration(e,s-1):{},a=this._measureChar(t,n,i,o);let h,c=a.kernedWidth,l=a.width;0!==this.charSpacing&&(h=this._getWidthOfCharSpacing(),l+=h,c+=h);const u={width:l,left:0,height:n.fontSize,kernedWidth:c,deltaY:n.deltaY};if(s>0&&!r){const t=this.__charBounds[e][s-1];u.left=t.left+t.width+a.kernedWidth-a.width}return u}getHeightOfLineImpl(t){const e=this.__lineHeights;if(e[t])return e[t];let s=this.getHeightOfChar(t,0);for(let e=1,i=this._textLines[t].length;e<i;e++)s=Math.max(this.getHeightOfChar(t,e),s);return e[t]=s*this._fontSizeMult}getHeightOfLine(t){return this.getHeightOfLineImpl(t)*this.lineHeight}calcTextHeight(){let t=0;for(let e=0,s=this._textLines.length;e<s;e++)t+=e===s-1?this.getHeightOfLineImpl(e):this.getHeightOfLine(e);return t}_getLeftOffset(){return"ltr"===this.direction?-this.width/2:this.width/2}_getTopOffset(){return-this.height/2}_renderTextCommon(t,e){t.save();let s=0;const i=this._getLeftOffset(),r=this._getTopOffset();for(let n=0,o=this._textLines.length;n<o;n++)this._renderTextLine(e,t,this._textLines[n],i+this._getLineLeftOffset(n),r+s+this.getHeightOfLineImpl(n),n),s+=this.getHeightOfLine(n);t.restore()}_renderTextFill(t){(this.fill||this.styleHas(K))&&this._renderTextCommon(t,"fillText")}_renderTextStroke(t){(this.stroke&&0!==this.strokeWidth||!this.isEmptyStyles())&&(this.shadow&&!this.shadow.affectStroke&&this._removeShadow(t),t.save(),this._setLineDash(t,this.strokeDashArray),t.beginPath(),this._renderTextCommon(t,"strokeText"),t.closePath(),t.restore())}_renderChars(t,e,s,i,r,n){const o=this.textAlign.includes(Je),a=this.path,h=!o&&0===this.charSpacing&&this.isEmptyStyles(n)&&!a,c="ltr"===this.direction,l="ltr"===this.direction?1:-1,u=e.direction;let d,g,f,p,m,v="",y=0;if(e.save(),u!==this.direction&&(e.canvas.setAttribute("dir",c?"ltr":"rtl"),e.direction=c?"ltr":"rtl",e.textAlign=c?M:A),r-=this.getHeightOfLineImpl(n)*this._fontSizeFraction,h)return this._renderChar(t,e,n,0,s.join(""),i,r),void e.restore();for(let h=0,c=s.length-1;h<=c;h++)p=h===c||this.charSpacing||a,v+=s[h],f=this.__charBounds[n][h],0===y?(i+=l*(f.kernedWidth-f.width),y+=f.width):y+=f.kernedWidth,o&&!p&&this._reSpaceAndTab.test(s[h])&&(p=!0),p||(d=d||this.getCompleteStyleDeclaration(n,h),g=this.getCompleteStyleDeclaration(n,h+1),p=Ki(d,g,!1)),p&&(a?(e.save(),e.translate(f.renderLeft,f.renderTop),e.rotate(f.angle),this._renderChar(t,e,n,h,v,-y/2,0),e.restore()):(m=i,this._renderChar(t,e,n,h,v,m,r)),v="",d=g,i+=l*y,y=0);e.restore()}_applyPatternGradientTransformText(t){const e=this.width+this.strokeWidth,s=this.height+this.strokeWidth,i=vt({width:e,height:s}),r=i.getContext("2d");return i.width=e,i.height=s,r.beginPath(),r.moveTo(0,0),r.lineTo(e,0),r.lineTo(e,s),r.lineTo(0,s),r.closePath(),r.translate(e/2,s/2),r.fillStyle=t.toLive(r),this._applyPatternGradientTransform(r,t),r.fill(),r.createPattern(i,"no-repeat")}handleFiller(t,e,s){let i,r;return Gt(s)?"percentage"===s.gradientUnits||s.gradientTransform||s.patternTransform?(i=-this.width/2,r=-this.height/2,t.translate(i,r),t[e]=this._applyPatternGradientTransformText(s),{offsetX:i,offsetY:r}):(t[e]=s.toLive(t),this._applyPatternGradientTransform(t,s)):(t[e]=s,{offsetX:0,offsetY:0})}_setStrokeStyles(t,e){let{stroke:s,strokeWidth:i}=e;return t.lineWidth=i,t.lineCap=this.strokeLineCap,t.lineDashOffset=this.strokeDashOffset,t.lineJoin=this.strokeLineJoin,t.miterLimit=this.strokeMiterLimit,this.handleFiller(t,"strokeStyle",s)}_setFillStyles(t,e){let{fill:s}=e;return this.handleFiller(t,"fillStyle",s)}_renderChar(t,e,s,i,r,n,o){const a=this._getStyleDeclaration(s,i),h=this.getCompleteStyleDeclaration(s,i),c="fillText"===t&&h.fill,l="strokeText"===t&&h.stroke&&h.strokeWidth;if(l||c){if(e.save(),e.font=this._getFontDeclaration(h),a.textBackgroundColor&&this._removeShadow(e),a.deltaY&&(o+=a.deltaY),c){const t=this._setFillStyles(e,h);e.fillText(r,n-t.offsetX,o-t.offsetY)}if(l){const t=this._setStrokeStyles(e,h);e.strokeText(r,n-t.offsetX,o-t.offsetY)}e.restore()}}setSuperscript(t,e){this._setScript(t,e,this.superscript)}setSubscript(t,e){this._setScript(t,e,this.subscript)}_setScript(t,e,s){const i=this.get2DCursorLocation(t,!0),r=this.getValueOfPropertyAt(i.lineIndex,i.charIndex,"fontSize"),n=this.getValueOfPropertyAt(i.lineIndex,i.charIndex,"deltaY"),o={fontSize:r*s.size,deltaY:n+r*s.baseline};this.setSelectionStyles(o,t,e)}_getLineLeftOffset(t){const e=this.getLineWidth(t),s=this.width-e,i=this.textAlign,r=this.direction,n=this.isEndOfWrapping(t);let o=0;return i===Je||i===$e&&!n||i===Ze&&!n||i===Qe&&!n?0:(i===D&&(o=s/2),i===A&&(o=s),i===$e&&(o=s/2),i===Ze&&(o=s),"rtl"===r&&(i===A||i===Je||i===Ze?o=0:i===M||i===Qe?o=-s:i!==D&&i!==$e||(o=-s/2)),o)}_clearCache(){this._forceClearCache=!1,this.__lineWidths=[],this.__lineHeights=[],this.__charBounds=[]}getLineWidth(t){if(void 0!==this.__lineWidths[t])return this.__lineWidths[t];const{width:e}=this.measureLine(t);return this.__lineWidths[t]=e,e}_getWidthOfCharSpacing(){return 0!==this.charSpacing?this.fontSize*this.charSpacing/1e3:0}getValueOfPropertyAt(t,e,s){var i;return null!==(i=this._getStyleDeclaration(t,e)[s])&&void 0!==i?i:this[s]}_renderTextDecoration(t,e){if(!this[e]&&!this.styleHas(e))return;let s=this._getTopOffset();const i=this._getLeftOffset(),r=this.path,n=this._getWidthOfCharSpacing(),o="linethrough"===e?.5:"overline"===e?1:0,a=this.offsets[e];for(let h=0,c=this._textLines.length;h<c;h++){const c=this.getHeightOfLine(h);if(!this[e]&&!this.styleHas(e,h)){s+=c;continue}const l=this._textLines[h],u=c/this.lineHeight,d=this._getLineLeftOffset(h);let g=0,f=0,p=this.getValueOfPropertyAt(h,0,e),m=this.getValueOfPropertyAt(h,0,K),v=this.getValueOfPropertyAt(h,0,ze),y=p,_=m,x=v;const C=s+u*(1-this._fontSizeFraction);let b=this.getHeightOfChar(h,0),S=this.getValueOfPropertyAt(h,0,"deltaY");for(let s=0,n=l.length;s<n;s++){const n=this.__charBounds[h][s];y=this.getValueOfPropertyAt(h,s,e),_=this.getValueOfPropertyAt(h,s,K),x=this.getValueOfPropertyAt(h,s,ze);const c=this.getHeightOfChar(h,s),l=this.getValueOfPropertyAt(h,s,"deltaY");if(r&&y&&_){const e=this.fontSize*x/1e3;t.save(),t.fillStyle=m,t.translate(n.renderLeft,n.renderTop),t.rotate(n.angle),t.fillRect(-n.kernedWidth/2,a*c+l-o*e,n.kernedWidth,e),t.restore()}else if((y!==p||_!==m||c!==b||x!==v||l!==S)&&f>0){const e=this.fontSize*v/1e3;let s=i+d+g;"rtl"===this.direction&&(s=this.width-s-f),p&&m&&v&&(t.fillStyle=m,t.fillRect(s,C+a*b+S-o*e,f,e)),g=n.left,f=n.width,p=y,v=x,m=_,b=c,S=l}else f+=n.kernedWidth}let w=i+d+g;"rtl"===this.direction&&(w=this.width-w-f),t.fillStyle=_;const T=this.fontSize*x/1e3;y&&_&&x&&t.fillRect(w,C+a*b+S-o*T,f-n,T),s+=c}this._removeShadow(t)}_getFontDeclaration(){let{fontFamily:t=this.fontFamily,fontStyle:e=this.fontStyle,fontWeight:s=this.fontWeight,fontSize:i=this.fontSize}=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{},r=arguments.length>1?arguments[1]:void 0;const n=t.includes("'")||t.includes('"')||t.includes(",")||Ao.genericFonts.includes(t.toLowerCase())?t:'"'.concat(t,'"');return[e,s,"".concat(r?this.CACHE_FONT_SIZE:i,"px"),n].join(" ")}render(t){this.visible&&(this.canvas&&this.canvas.skipOffscreen&&!this.group&&!this.isOnScreen()||(this._forceClearCache&&this.initDimensions(),super.render(t)))}graphemeSplit(t){return Hi(t)}_splitTextIntoLines(t){const e=t.split(this._reNewline),s=new Array(e.length),i=["\n"];let r=[];for(let t=0;t<e.length;t++)s[t]=this.graphemeSplit(e[t]),r=r.concat(s[t],i);return r.pop(),{_unwrappedLines:s,lines:e,graphemeText:r,graphemeLines:s}}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return s(s({},super.toObject([...Ue,...t])),{},{styles:Ji(this.styles,this.text)},this.path?{path:this.path.toObject()}:{})}set(t,e){const{textLayoutProperties:s}=this.constructor;super.set(t,e);let i=!1,r=!1;if("object"==typeof t)for(const e in t)"path"===e&&this.setPathInfo(),i=i||s.includes(e),r=r||"path"===e;else i=s.includes(t),r="path"===t;return r&&this.setPathInfo(),i&&this.initialized&&(this.initDimensions(),this.setCoords()),this}complexity(){return 1}static async fromElement(t,e,r){const n=Pr(t,Ao.ATTRIBUTE_NAMES,r),o=s(s({},e),n),{textAnchor:a=M,textDecoration:h="",dx:c=0,dy:l=0,top:u=0,left:d=0,fontSize:g=O,strokeWidth:f=1}=o,p=i(o,Po),m=new this(Pe(t.textContent||"").trim(),s({left:d+c,top:u+l,underline:h.includes("underline"),overline:h.includes("overline"),linethrough:h.includes("line-through"),strokeWidth:0,fontSize:g},p)),v=m.getScaledHeight()/m.height,y=((m.height+m.strokeWidth)*m.lineHeight-m.height)*v,_=m.getScaledHeight()+y;let x=0;return a===D&&(x=m.getScaledWidth()/2),a===A&&(x=m.getScaledWidth()),m.set({left:m.left-x,top:m.top-(_-m.fontSize*(.07+m._fontSizeFraction))/m.lineHeight,strokeWidth:f}),m}static fromObject(t){return this._fromObject(s(s({},t),{},{styles:Qi(t.styles||{},t.text)}),{extraParam:"text"})}}t(Ao,"textLayoutProperties",Ne),t(Ao,"cacheProperties",[...Es,...Ue]),t(Ao,"ownDefaults",Ke),t(Ao,"type","Text"),t(Ao,"genericFonts",["serif","sans-serif","monospace","cursive","fantasy","system-ui","ui-serif","ui-sans-serif","ui-monospace","ui-rounded","math","emoji","fangsong"]),t(Ao,"ATTRIBUTE_NAMES",Zi.concat("x","y","dx","dy","font-family","font-style","font-weight","font-size","letter-spacing","text-decoration","text-anchor")),Fi(Ao,[class extends We{_toSVG(){const t=this._getSVGLeftTopOffsets(),e=this._getSVGTextAndBg(t.textTop,t.textLeft);return this._wrapSVGTextAndBg(e)}toSVG(t){const e=this._createBaseSVGMarkup(this._toSVG(),{reviver:t,noStyle:!0,withShadow:!0}),s=this.path;return s?e+s._createBaseSVGMarkup(s._toSVG(),{reviver:t,withShadow:!0,additionalTransform:zt(this.calcOwnMatrix())}):e}_getSVGLeftTopOffsets(){return{textLeft:-this.width/2,textTop:-this.height/2,lineTop:this.getHeightOfLine(0)}}_wrapSVGTextAndBg(t){let{textBgRects:e,textSpans:s}=t;const i=this.getSvgTextDecoration(this);return[e.join(""),'\t\t<text xml:space="preserve" ','font-family="'.concat(this.fontFamily.replace(Do,"'"),'" '),'font-size="'.concat(this.fontSize,'" '),this.fontStyle?'font-style="'.concat(this.fontStyle,'" '):"",this.fontWeight?'font-weight="'.concat(this.fontWeight,'" '):"",i?'text-decoration="'.concat(i,'" '):"","rtl"===this.direction?'direction="'.concat(this.direction,'" '):"",'style="',this.getSvgStyles(!0),'"',this.addPaintOrder()," >",s.join(""),"</text>\n"]}_getSVGTextAndBg(t,e){const s=[],i=[];let r,n=t;this.backgroundColor&&i.push(...Mo(this.backgroundColor,-this.width/2,-this.height/2,this.width,this.height));for(let t=0,o=this._textLines.length;t<o;t++)r=this._getLineLeftOffset(t),"rtl"===this.direction&&(r+=this.width),(this.textBackgroundColor||this.styleHas("textBackgroundColor",t))&&this._setSVGTextLineBg(i,t,e+r,n),this._setSVGTextLineText(s,t,e+r,n),n+=this.getHeightOfLine(t);return{textSpans:s,textBgRects:i}}_createTextCharSpan(t,e,s,i,r){const n=o.NUM_FRACTION_DIGITS,a=this.getSvgSpanStyles(e,t!==t.trim()||!!t.match(ko)),h=a?'style="'.concat(a,'"'):"",c=e.deltaY,l=c?' dy="'.concat(Vt(c,n),'" '):"",{angle:u,renderLeft:d,renderTop:g,width:f}=r;let p="";if(void 0!==d){const t=f/2;u&&(p=' rotate="'.concat(Vt(Ct(u),n),'"'));const e=Pt({angle:Ct(u)});e[4]=d,e[5]=g;const r=new ot(-t,0).transform(e);s=r.x,i=r.y}return'<tspan x="'.concat(Vt(s,n),'" y="').concat(Vt(i,n),'" ').concat(l).concat(p).concat(h,">").concat(zi(t),"</tspan>")}_setSVGTextLineText(t,e,s,i){const r=this.getHeightOfLine(e),n=this.textAlign.includes(Je),o=this._textLines[e];let a,h,c,l,u,d="",g=0;i+=r*(1-this._fontSizeFraction)/this.lineHeight;for(let r=0,f=o.length-1;r<=f;r++)u=r===f||this.charSpacing||this.path,d+=o[r],c=this.__charBounds[e][r],0===g?(s+=c.kernedWidth-c.width,g+=c.width):g+=c.kernedWidth,n&&!u&&this._reSpaceAndTab.test(o[r])&&(u=!0),u||(a=a||this.getCompleteStyleDeclaration(e,r),h=this.getCompleteStyleDeclaration(e,r+1),u=Ki(a,h,!0)),u&&(l=this._getStyleDeclaration(e,r),t.push(this._createTextCharSpan(d,l,s,i,c)),d="",a=h,"rtl"===this.direction?s-=g:s+=g,g=0)}_setSVGTextLineBg(t,e,s,i){const r=this._textLines[e],n=this.getHeightOfLine(e)/this.lineHeight;let o,a=0,h=0,c=this.getValueOfPropertyAt(e,0,"textBackgroundColor");for(let l=0;l<r.length;l++){const{left:r,width:u,kernedWidth:d}=this.__charBounds[e][l];o=this.getValueOfPropertyAt(e,l,"textBackgroundColor"),o!==c?(c&&t.push(...Mo(c,s+h,i,a,n)),h=r,a=u,c=o):a+=d}o&&t.push(...Mo(c,s+h,i,a,n))}_getSVGLineTopOffset(t){let e,s=0;for(e=0;e<t;e++)s+=this.getHeightOfLine(e);const i=this.getHeightOfLine(e);return{lineTop:s,offset:(this._fontSizeMult-this._fontSizeFraction)*i/(this.lineHeight*this._fontSizeMult)}}getSvgStyles(t){return"".concat(super.getSvgStyles(t)," text-decoration-thickness: ").concat(Vt(this.textDecorationThickness*this.getObjectScaling().y/10,o.NUM_FRACTION_DIGITS),"%; white-space: pre;")}getSvgSpanStyles(t,e){const{fontFamily:s,strokeWidth:i,stroke:r,fill:n,fontSize:a,fontStyle:h,fontWeight:c,deltaY:l,textDecorationThickness:u,linethrough:d,overline:g,underline:f}=t,p=this.getSvgTextDecoration({underline:null!=f?f:this.underline,overline:null!=g?g:this.overline,linethrough:null!=d?d:this.linethrough}),m=u||this.textDecorationThickness;return[r?Ye(J,r):"",i?"stroke-width: ".concat(i,"; "):"",s?"font-family: ".concat(s.includes("'")||s.includes('"')?s:"'".concat(s,"'"),"; "):"",a?"font-size: ".concat(a,"px; "):"",h?"font-style: ".concat(h,"; "):"",c?"font-weight: ".concat(c,"; "):"",p?"text-decoration: ".concat(p,"; text-decoration-thickness: ").concat(Vt(m*this.getObjectScaling().y/10,o.NUM_FRACTION_DIGITS),"%; "):"",n?Ye(K,n):"",l?"baseline-shift: ".concat(-l,"; "):"",e?"white-space: pre; ":""].join("")}getSvgTextDecoration(t){return["overline","underline","line-through"].filter((e=>t[e.replace("-","")])).join(" ")}}]),tt.setClass(Ao),tt.setSVGClass(Ao);class jo{constructor(e){t(this,"target",void 0),t(this,"__mouseDownInPlace",!1),t(this,"__dragStartFired",!1),t(this,"__isDraggingOver",!1),t(this,"__dragStartSelection",void 0),t(this,"__dragImageDisposer",void 0),t(this,"_dispose",void 0),this.target=e;const s=[this.target.on("dragenter",this.dragEnterHandler.bind(this)),this.target.on("dragover",this.dragOverHandler.bind(this)),this.target.on("dragleave",this.dragLeaveHandler.bind(this)),this.target.on("dragend",this.dragEndHandler.bind(this)),this.target.on("drop",this.dropHandler.bind(this))];this._dispose=()=>{s.forEach((t=>t())),this._dispose=void 0}}isPointerOverSelection(t){const e=this.target,s=e.getSelectionStartFromPointer(t);return e.isEditing&&s>=e.selectionStart&&s<=e.selectionEnd&&e.selectionStart<e.selectionEnd}start(t){return this.__mouseDownInPlace=this.isPointerOverSelection(t)}isActive(){return this.__mouseDownInPlace}end(t){const e=this.isActive();return e&&!this.__dragStartFired&&(this.target.setCursorByClick(t),this.target.initDelayedCursor(!0)),this.__mouseDownInPlace=!1,this.__dragStartFired=!1,this.__isDraggingOver=!1,e}getDragStartSelection(){return this.__dragStartSelection}setDragImage(t,e){var s;let{selectionStart:i,selectionEnd:r}=e;const n=this.target,o=n.canvas,a=new ot(n.flipX?-1:1,n.flipY?-1:1),h=n._getCursorBoundaries(i),c=new ot(h.left+h.leftOffset,h.top+h.topOffset).multiply(a).transform(n.calcTransformMatrix()),l=o.getScenePoint(t).subtract(c),u=n.getCanvasRetinaScaling(),d=n.getBoundingRect(),g=c.subtract(new ot(d.left,d.top)),f=o.viewportTransform,p=g.add(l).transform(f,!0),m=n.backgroundColor,v=Vi(n.styles);n.backgroundColor="";const y={stroke:"transparent",fill:"transparent",textBackgroundColor:"transparent"};n.setSelectionStyles(y,0,i),n.setSelectionStyles(y,r,n.text.length),n.dirty=!0;const _=n.toCanvasElement({enableRetinaScaling:o.enableRetinaScaling,viewportTransform:!0});n.backgroundColor=m,n.styles=v,n.dirty=!0,Tn(_,{position:"fixed",left:"".concat(-_.width,"px"),border:j,width:"".concat(_.width/u,"px"),height:"".concat(_.height/u,"px")}),this.__dragImageDisposer&&this.__dragImageDisposer(),this.__dragImageDisposer=()=>{_.remove()},Kt(t.target||this.target.hiddenTextarea).body.appendChild(_),null===(s=t.dataTransfer)||void 0===s||s.setDragImage(_,p.x,p.y)}onDragStart(t){this.__dragStartFired=!0;const e=this.target,i=this.isActive();if(i&&t.dataTransfer){const i=this.__dragStartSelection={selectionStart:e.selectionStart,selectionEnd:e.selectionEnd},r=e._text.slice(i.selectionStart,i.selectionEnd).join(""),n=s({text:e.text,value:r},i);t.dataTransfer.setData("text/plain",r),t.dataTransfer.setData("application/fabric",JSON.stringify({value:r,styles:e.getSelectionStyles(i.selectionStart,i.selectionEnd,!0)})),t.dataTransfer.effectAllowed="copyMove",this.setDragImage(t,n)}return e.abortCursorAnimation(),i}canDrop(t){if(this.target.editable&&!this.target.getActiveControl()&&!t.defaultPrevented){if(this.isActive()&&this.__dragStartSelection){const e=this.target.getSelectionStartFromPointer(t),s=this.__dragStartSelection;return e<s.selectionStart||e>s.selectionEnd}return!0}return!1}targetCanDrop(t){return this.target.canDrop(t)}dragEnterHandler(t){let{e:e}=t;const s=this.targetCanDrop(e);!this.__isDraggingOver&&s&&(this.__isDraggingOver=!0)}dragOverHandler(t){const{e:e}=t,s=this.targetCanDrop(e);!this.__isDraggingOver&&s?this.__isDraggingOver=!0:this.__isDraggingOver&&!s&&(this.__isDraggingOver=!1),this.__isDraggingOver&&(e.preventDefault(),t.canDrop=!0,t.dropTarget=this.target)}dragLeaveHandler(){(this.__isDraggingOver||this.isActive())&&(this.__isDraggingOver=!1)}dropHandler(t){var e;const{e:s}=t,i=s.defaultPrevented;this.__isDraggingOver=!1,s.preventDefault();let r=null===(e=s.dataTransfer)||void 0===e?void 0:e.getData("text/plain");if(r&&!i){const e=this.target,i=e.canvas;let n=e.getSelectionStartFromPointer(s);const{styles:o}=s.dataTransfer.types.includes("application/fabric")?JSON.parse(s.dataTransfer.getData("application/fabric")):{},a=r[Math.max(0,r.length-1)],h=0;if(this.__dragStartSelection){const t=this.__dragStartSelection.selectionStart,s=this.__dragStartSelection.selectionEnd;n>t&&n<=s?n=t:n>s&&(n-=s-t),e.removeChars(t,s),delete this.__dragStartSelection}e._reNewline.test(a)&&(e._reNewline.test(e._text[n])||n===e._text.length)&&(r=r.trimEnd()),t.didDrop=!0,t.dropTarget=e,e.insertChars(r,o,n),i.setActiveObject(e),e.enterEditing(s),e.selectionStart=Math.min(n+h,e._text.length),e.selectionEnd=Math.min(e.selectionStart+r.length,e._text.length),e.hiddenTextarea.value=e.text,e._updateTextarea(),e.hiddenTextarea.focus(),e.fire(z,{index:n+h,action:"drop"}),i.fire("text:changed",{target:e}),i.contextTopDirty=!0,i.requestRenderAll()}}dragEndHandler(t){let{e:e}=t;if(this.isActive()&&this.__dragStartFired&&this.__dragStartSelection){var s;const t=this.target,i=this.target.canvas,{selectionStart:r,selectionEnd:n}=this.__dragStartSelection,o=(null===(s=e.dataTransfer)||void 0===s?void 0:s.dropEffect)||j;o===j?(t.selectionStart=r,t.selectionEnd=n,t._updateTextarea(),t.hiddenTextarea.focus()):(t.clearContextTop(),"move"===o&&(t.removeChars(r,n),t.selectionStart=t.selectionEnd=r,t.hiddenTextarea&&(t.hiddenTextarea.value=t.text),t._updateTextarea(),t.fire(z,{index:r,action:"dragend"}),i.fire("text:changed",{target:t}),i.requestRenderAll()),t.exitEditing())}this.__dragImageDisposer&&this.__dragImageDisposer(),delete this.__dragImageDisposer,delete this.__dragStartSelection,this.__isDraggingOver=!1}dispose(){this._dispose&&this._dispose()}}const Fo=/[ \n\.,;!\?\-]/;class Lo extends Ao{constructor(){super(...arguments),t(this,"_currentCursorOpacity",1)}initBehavior(){this._tick=this._tick.bind(this),this._onTickComplete=this._onTickComplete.bind(this),this.updateSelectionOnMouseMove=this.updateSelectionOnMouseMove.bind(this)}onDeselect(t){return this.isEditing&&this.exitEditing(),this.selected=!1,super.onDeselect(t)}_animateCursor(t){let{toValue:e,duration:s,delay:i,onComplete:r}=t;return Ks({startValue:this._currentCursorOpacity,endValue:e,duration:s,delay:i,onComplete:r,abort:()=>!this.canvas||this.selectionStart!==this.selectionEnd,onChange:t=>{this._currentCursorOpacity=t,this.renderCursorOrSelection()}})}_tick(t){this._currentTickState=this._animateCursor({toValue:0,duration:this.cursorDuration/2,delay:Math.max(t||0,100),onComplete:this._onTickComplete})}_onTickComplete(){var t;null===(t=this._currentTickCompleteState)||void 0===t||t.abort(),this._currentTickCompleteState=this._animateCursor({toValue:1,duration:this.cursorDuration,onComplete:this._tick})}initDelayedCursor(t){this.abortCursorAnimation(),this._tick(t?0:this.cursorDelay)}abortCursorAnimation(){let t=!1;[this._currentTickState,this._currentTickCompleteState].forEach((e=>{e&&!e.isDone()&&(t=!0,e.abort())})),this._currentCursorOpacity=1,t&&this.clearContextTop()}restartCursorIfNeeded(){[this._currentTickState,this._currentTickCompleteState].some((t=>!t||t.isDone()))&&this.initDelayedCursor()}selectAll(){return this.selectionStart=0,this.selectionEnd=this._text.length,this._fireSelectionChanged(),this._updateTextarea(),this}cmdAll(){this.selectAll(),this.renderCursorOrSelection()}getSelectedText(){return this._text.slice(this.selectionStart,this.selectionEnd).join("")}findWordBoundaryLeft(t){let e=0,s=t-1;if(this._reSpace.test(this._text[s]))for(;this._reSpace.test(this._text[s]);)e++,s--;for(;/\S/.test(this._text[s])&&s>-1;)e++,s--;return t-e}findWordBoundaryRight(t){let e=0,s=t;if(this._reSpace.test(this._text[s]))for(;this._reSpace.test(this._text[s]);)e++,s++;for(;/\S/.test(this._text[s])&&s<this._text.length;)e++,s++;return t+e}findLineBoundaryLeft(t){let e=0,s=t-1;for(;!/\n/.test(this._text[s])&&s>-1;)e++,s--;return t-e}findLineBoundaryRight(t){let e=0,s=t;for(;!/\n/.test(this._text[s])&&s<this._text.length;)e++,s++;return t+e}searchWordBoundary(t,e){const s=this._text;let i=t>0&&this._reSpace.test(s[t])&&(-1===e||!F.test(s[t-1]))?t-1:t,r=s[i];for(;i>0&&i<s.length&&!Fo.test(r);)i+=e,r=s[i];return-1===e&&Fo.test(r)&&i++,i}selectWord(t){var e;t=null!==(e=t)&&void 0!==e?e:this.selectionStart;const s=this.searchWordBoundary(t,-1),i=Math.max(s,this.searchWordBoundary(t,1));this.selectionStart=s,this.selectionEnd=i,this._fireSelectionChanged(),this._updateTextarea(),this.renderCursorOrSelection()}selectLine(t){var e;t=null!==(e=t)&&void 0!==e?e:this.selectionStart;const s=this.findLineBoundaryLeft(t),i=this.findLineBoundaryRight(t);this.selectionStart=s,this.selectionEnd=i,this._fireSelectionChanged(),this._updateTextarea()}enterEditing(t){!this.isEditing&&this.editable&&(this.enterEditingImpl(),this.fire("editing:entered",t?{e:t}:void 0),this._fireSelectionChanged(),this.canvas&&(this.canvas.fire("text:editing:entered",{target:this,e:t}),this.canvas.requestRenderAll()))}enterEditingImpl(){this.canvas&&(this.canvas.calcOffset(),this.canvas.textEditingManager.exitTextEditing()),this.isEditing=!0,this.initHiddenTextarea(),this.hiddenTextarea.focus(),this.hiddenTextarea.value=this.text,this._updateTextarea(),this._saveEditingProps(),this._setEditingProps(),this._textBeforeEdit=this.text,this._tick()}updateSelectionOnMouseMove(t){if(this.getActiveControl())return;const e=this.hiddenTextarea;Kt(e).activeElement!==e&&e.focus();const s=this.getSelectionStartFromPointer(t),i=this.selectionStart,r=this.selectionEnd;(s===this.__selectionStartOnMouseDown&&i!==r||i!==s&&r!==s)&&(s>this.__selectionStartOnMouseDown?(this.selectionStart=this.__selectionStartOnMouseDown,this.selectionEnd=s):(this.selectionStart=s,this.selectionEnd=this.__selectionStartOnMouseDown),this.selectionStart===i&&this.selectionEnd===r||(this._fireSelectionChanged(),this._updateTextarea(),this.renderCursorOrSelection()))}_setEditingProps(){this.hoverCursor="text",this.canvas&&(this.canvas.defaultCursor=this.canvas.moveCursor="text"),this.borderColor=this.editingBorderColor,this.hasControls=this.selectable=!1,this.lockMovementX=this.lockMovementY=!0}fromStringToGraphemeSelection(t,e,s){const i=s.slice(0,t),r=this.graphemeSplit(i).length;if(t===e)return{selectionStart:r,selectionEnd:r};const n=s.slice(t,e);return{selectionStart:r,selectionEnd:r+this.graphemeSplit(n).length}}fromGraphemeToStringSelection(t,e,s){const i=s.slice(0,t).join("").length;if(t===e)return{selectionStart:i,selectionEnd:i};return{selectionStart:i,selectionEnd:i+s.slice(t,e).join("").length}}_updateTextarea(){if(this.cursorOffsetCache={},this.hiddenTextarea){if(!this.inCompositionMode){const t=this.fromGraphemeToStringSelection(this.selectionStart,this.selectionEnd,this._text);this.hiddenTextarea.selectionStart=t.selectionStart,this.hiddenTextarea.selectionEnd=t.selectionEnd}this.updateTextareaPosition()}}updateFromTextArea(){if(!this.hiddenTextarea)return;this.cursorOffsetCache={};const t=this.hiddenTextarea;this.text=t.value,this.set("dirty",!0),this.initDimensions(),this.setCoords();const e=this.fromStringToGraphemeSelection(t.selectionStart,t.selectionEnd,t.value);this.selectionEnd=this.selectionStart=e.selectionEnd,this.inCompositionMode||(this.selectionStart=e.selectionStart),this.updateTextareaPosition()}updateTextareaPosition(){if(this.selectionStart===this.selectionEnd){const t=this._calcTextareaPosition();this.hiddenTextarea.style.left=t.left,this.hiddenTextarea.style.top=t.top}}_calcTextareaPosition(){if(!this.canvas)return{left:"1px",top:"1px"};const t=this.inCompositionMode?this.compositionStart:this.selectionStart,e=this._getCursorBoundaries(t),s=this.get2DCursorLocation(t),i=s.lineIndex,r=s.charIndex,n=this.getValueOfPropertyAt(i,r,"fontSize")*this.lineHeight,o=e.leftOffset,a=this.getCanvasRetinaScaling(),h=this.canvas.upperCanvasEl,c=h.width/a,l=h.height/a,u=c-n,d=l-n,g=new ot(e.left+o,e.top+e.topOffset+n).transform(this.calcTransformMatrix()).transform(this.canvas.viewportTransform).multiply(new ot(h.clientWidth/c,h.clientHeight/l));return g.x<0&&(g.x=0),g.x>u&&(g.x=u),g.y<0&&(g.y=0),g.y>d&&(g.y=d),g.x+=this.canvas._offset.left,g.y+=this.canvas._offset.top,{left:"".concat(g.x,"px"),top:"".concat(g.y,"px"),fontSize:"".concat(n,"px"),charHeight:n}}_saveEditingProps(){this._savedProps={hasControls:this.hasControls,borderColor:this.borderColor,lockMovementX:this.lockMovementX,lockMovementY:this.lockMovementY,hoverCursor:this.hoverCursor,selectable:this.selectable,defaultCursor:this.canvas&&this.canvas.defaultCursor,moveCursor:this.canvas&&this.canvas.moveCursor}}_restoreEditingProps(){this._savedProps&&(this.hoverCursor=this._savedProps.hoverCursor,this.hasControls=this._savedProps.hasControls,this.borderColor=this._savedProps.borderColor,this.selectable=this._savedProps.selectable,this.lockMovementX=this._savedProps.lockMovementX,this.lockMovementY=this._savedProps.lockMovementY,this.canvas&&(this.canvas.defaultCursor=this._savedProps.defaultCursor||this.canvas.defaultCursor,this.canvas.moveCursor=this._savedProps.moveCursor||this.canvas.moveCursor),delete this._savedProps)}_exitEditing(){const t=this.hiddenTextarea;this.selected=!1,this.isEditing=!1,t&&(t.blur&&t.blur(),t.parentNode&&t.parentNode.removeChild(t)),this.hiddenTextarea=null,this.abortCursorAnimation(),this.selectionStart!==this.selectionEnd&&this.clearContextTop()}exitEditingImpl(){this._exitEditing(),this.selectionEnd=this.selectionStart,this._restoreEditingProps(),this._forceClearCache&&(this.initDimensions(),this.setCoords())}exitEditing(){const t=this._textBeforeEdit!==this.text;return this.exitEditingImpl(),this.fire("editing:exited"),t&&this.fire(Q),this.canvas&&(this.canvas.fire("text:editing:exited",{target:this}),t&&this.canvas.fire("object:modified",{target:this})),this}_removeExtraneousStyles(){for(const t in this.styles)this._textLines[t]||delete this.styles[t]}removeStyleFromTo(t,e){const{lineIndex:s,charIndex:i}=this.get2DCursorLocation(t,!0),{lineIndex:r,charIndex:n}=this.get2DCursorLocation(e,!0);if(s!==r){if(this.styles[s])for(let t=i;t<this._unwrappedTextLines[s].length;t++)delete this.styles[s][t];if(this.styles[r])for(let t=n;t<this._unwrappedTextLines[r].length;t++){const e=this.styles[r][t];e&&(this.styles[s]||(this.styles[s]={}),this.styles[s][i+t-n]=e)}for(let t=s+1;t<=r;t++)delete this.styles[t];this.shiftLineStyles(r,s-r)}else if(this.styles[s]){const t=this.styles[s],e=n-i;for(let e=i;e<n;e++)delete t[e];for(const i in this.styles[s]){const s=parseInt(i,10);s>=n&&(t[s-e]=t[i],delete t[i])}}}shiftLineStyles(t,e){const s=Object.assign({},this.styles);for(const i in this.styles){const r=parseInt(i,10);r>t&&(this.styles[r+e]=s[r],s[r-e]||delete this.styles[r])}}insertNewlineStyleObject(t,e,i,r){const n={},o=this._unwrappedTextLines[t].length,a=o===e;let h=!1;i||(i=1),this.shiftLineStyles(t,i);const c=this.styles[t]?this.styles[t][0===e?e:e-1]:void 0;for(const s in this.styles[t]){const i=parseInt(s,10);i>=e&&(h=!0,n[i-e]=this.styles[t][s],a&&0===e||delete this.styles[t][s])}let l=!1;for(h&&!a&&(this.styles[t+i]=n,l=!0),(l||o>e)&&i--;i>0;)r&&r[i-1]?this.styles[t+i]={0:s({},r[i-1])}:c?this.styles[t+i]={0:s({},c)}:delete this.styles[t+i],i--;this._forceClearCache=!0}insertCharStyleObject(t,e,i,r){this.styles||(this.styles={});const n=this.styles[t],o=n?s({},n):{};i||(i=1);for(const t in o){const s=parseInt(t,10);s>=e&&(n[s+i]=o[s],o[s-i]||delete n[s])}if(this._forceClearCache=!0,r){for(;i--;)Object.keys(r[i]).length&&(this.styles[t]||(this.styles[t]={}),this.styles[t][e+i]=s({},r[i]));return}if(!n)return;const a=n[e?e-1:1];for(;a&&i--;)this.styles[t][e+i]=s({},a)}insertNewStyleBlock(t,e,s){const i=this.get2DCursorLocation(e,!0),r=[0];let n,o=0;for(let e=0;e<t.length;e++)"\n"===t[e]?(o++,r[o]=0):r[o]++;for(r[0]>0&&(this.insertCharStyleObject(i.lineIndex,i.charIndex,r[0],s),s=s&&s.slice(r[0]+1)),o&&this.insertNewlineStyleObject(i.lineIndex,i.charIndex+r[0],o),n=1;n<o;n++)r[n]>0?this.insertCharStyleObject(i.lineIndex+n,0,r[n],s):s&&this.styles[i.lineIndex+n]&&s[0]&&(this.styles[i.lineIndex+n][0]=s[0]),s=s&&s.slice(r[n]+1);r[n]>0&&this.insertCharStyleObject(i.lineIndex+n,0,r[n],s)}removeChars(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:t+1;this.removeStyleFromTo(t,e),this._text.splice(t,e-t),this.text=this._text.join(""),this.set("dirty",!0),this.initDimensions(),this.setCoords(),this._removeExtraneousStyles()}insertChars(t,e,s){let i=arguments.length>3&&void 0!==arguments[3]?arguments[3]:s;i>s&&this.removeStyleFromTo(s,i);const r=this.graphemeSplit(t);this.insertNewStyleBlock(r,s,e),this._text=[...this._text.slice(0,s),...r,...this._text.slice(i)],this.text=this._text.join(""),this.set("dirty",!0),this.initDimensions(),this.setCoords(),this._removeExtraneousStyles()}setSelectionStartEndWithShift(t,e,s){s<=t?(e===t?this._selectionDirection=M:this._selectionDirection===A&&(this._selectionDirection=M,this.selectionEnd=t),this.selectionStart=s):s>t&&s<e?this._selectionDirection===A?this.selectionEnd=s:this.selectionStart=s:(e===t?this._selectionDirection=A:this._selectionDirection===M&&(this._selectionDirection=A,this.selectionStart=e),this.selectionEnd=s)}}class Ro extends Lo{initHiddenTextarea(){const t=this.canvas&&Kt(this.canvas.getElement())||m(),e=t.createElement("textarea");Object.entries({autocapitalize:"off",autocorrect:"off",autocomplete:"off",spellcheck:"false","data-fabric":"textarea",wrap:"off",name:"fabricTextarea"}).map((t=>{let[s,i]=t;return e.setAttribute(s,i)}));const{top:s,left:i,fontSize:r}=this._calcTextareaPosition();e.style.cssText="position: absolute; top: ".concat(s,"; left: ").concat(i,"; z-index: -999; opacity: 0; width: 1px; height: 1px; font-size: 1px; padding-top: ").concat(r,";"),(this.hiddenTextareaContainer||t.body).appendChild(e),Object.entries({blur:"blur",keydown:"onKeyDown",keyup:"onKeyUp",input:"onInput",copy:"copy",cut:"copy",paste:"paste",compositionstart:"onCompositionStart",compositionupdate:"onCompositionUpdate",compositionend:"onCompositionEnd"}).map((t=>{let[s,i]=t;return e.addEventListener(s,this[i].bind(this))})),this.hiddenTextarea=e}blur(){this.abortCursorAnimation()}onKeyDown(t){if(!this.isEditing)return;const e="rtl"===this.direction?this.keysMapRtl:this.keysMap;if(t.keyCode in e)this[e[t.keyCode]](t);else{if(!(t.keyCode in this.ctrlKeysMapDown)||!t.ctrlKey&&!t.metaKey)return;this[this.ctrlKeysMapDown[t.keyCode]](t)}t.stopImmediatePropagation(),t.preventDefault(),t.keyCode>=33&&t.keyCode<=40?(this.inCompositionMode=!1,this.clearContextTop(),this.renderCursorOrSelection()):this.canvas&&this.canvas.requestRenderAll()}onKeyUp(t){!this.isEditing||this._copyDone||this.inCompositionMode?this._copyDone=!1:t.keyCode in this.ctrlKeysMapUp&&(t.ctrlKey||t.metaKey)&&(this[this.ctrlKeysMapUp[t.keyCode]](t),t.stopImmediatePropagation(),t.preventDefault(),this.canvas&&this.canvas.requestRenderAll())}onInput(t){const e=this.fromPaste,{value:s,selectionStart:i,selectionEnd:r}=this.hiddenTextarea;if(this.fromPaste=!1,t&&t.stopPropagation(),!this.isEditing)return;const n=()=>{this.updateFromTextArea(),this.fire(z),this.canvas&&(this.canvas.fire("text:changed",{target:this}),this.canvas.requestRenderAll())};if(""===this.hiddenTextarea.value)return this.styles={},void n();const a=this._splitTextIntoLines(s).graphemeText,h=this._text.length,c=a.length,l=this.selectionStart,u=this.selectionEnd,d=l!==u;let g,f,m,v,y=c-h;const _=this.fromStringToGraphemeSelection(i,r,s),x=l>_.selectionStart;d?(f=this._text.slice(l,u),y+=u-l):c<h&&(f=x?this._text.slice(u+y,u):this._text.slice(l,l-y));const C=a.slice(_.selectionEnd-y,_.selectionEnd);if(f&&f.length&&(C.length&&(g=this.getSelectionStyles(l,l+1,!1),g=C.map((()=>g[0]))),d?(m=l,v=u):x?(m=u-f.length,v=u):(m=u,v=u+f.length),this.removeStyleFromTo(m,v)),C.length){const{copyPasteData:t}=p();e&&C.join("")===t.copiedText&&!o.disableStyleCopyPaste&&(g=t.copiedTextStyle),this.insertNewStyleBlock(C,l,g)}n()}onCompositionStart(){this.inCompositionMode=!0}onCompositionEnd(){this.inCompositionMode=!1}onCompositionUpdate(t){let{target:e}=t;const{selectionStart:s,selectionEnd:i}=e;this.compositionStart=s,this.compositionEnd=i,this.updateTextareaPosition()}copy(){if(this.selectionStart===this.selectionEnd)return;const{copyPasteData:t}=p();t.copiedText=this.getSelectedText(),o.disableStyleCopyPaste?t.copiedTextStyle=void 0:t.copiedTextStyle=this.getSelectionStyles(this.selectionStart,this.selectionEnd,!0),this._copyDone=!0}paste(){this.fromPaste=!0}_getWidthBeforeCursor(t,e){let s,i=this._getLineLeftOffset(t);return e>0&&(s=this.__charBounds[t][e-1],i+=s.left+s.width),i}getDownCursorOffset(t,e){const s=this._getSelectionForOffset(t,e),i=this.get2DCursorLocation(s),r=i.lineIndex;if(r===this._textLines.length-1||t.metaKey||34===t.keyCode)return this._text.length-s;const n=i.charIndex,o=this._getWidthBeforeCursor(r,n),a=this._getIndexOnLine(r+1,o);return this._textLines[r].slice(n).length+a+1+this.missingNewlineOffset(r)}_getSelectionForOffset(t,e){return t.shiftKey&&this.selectionStart!==this.selectionEnd&&e?this.selectionEnd:this.selectionStart}getUpCursorOffset(t,e){const s=this._getSelectionForOffset(t,e),i=this.get2DCursorLocation(s),r=i.lineIndex;if(0===r||t.metaKey||33===t.keyCode)return-s;const n=i.charIndex,o=this._getWidthBeforeCursor(r,n),a=this._getIndexOnLine(r-1,o),h=this._textLines[r].slice(0,n),c=this.missingNewlineOffset(r-1);return-this._textLines[r-1].length+a-h.length+(1-c)}_getIndexOnLine(t,e){const s=this._textLines[t];let i,r,n=this._getLineLeftOffset(t),o=0;for(let a=0,h=s.length;a<h;a++)if(i=this.__charBounds[t][a].width,n+=i,n>e){r=!0;const t=n-i,s=n,h=Math.abs(t-e);o=Math.abs(s-e)<h?a:a-1;break}return r||(o=s.length-1),o}moveCursorDown(t){this.selectionStart>=this._text.length&&this.selectionEnd>=this._text.length||this._moveCursorUpOrDown("Down",t)}moveCursorUp(t){0===this.selectionStart&&0===this.selectionEnd||this._moveCursorUpOrDown("Up",t)}_moveCursorUpOrDown(t,e){const s=this["get".concat(t,"CursorOffset")](e,this._selectionDirection===A);if(e.shiftKey?this.moveCursorWithShift(s):this.moveCursorWithoutShift(s),0!==s){const t=this.text.length;this.selectionStart=Ms(0,this.selectionStart,t),this.selectionEnd=Ms(0,this.selectionEnd,t),this.abortCursorAnimation(),this.initDelayedCursor(),this._fireSelectionChanged(),this._updateTextarea()}}moveCursorWithShift(t){const e=this._selectionDirection===M?this.selectionStart+t:this.selectionEnd+t;return this.setSelectionStartEndWithShift(this.selectionStart,this.selectionEnd,e),0!==t}moveCursorWithoutShift(t){return t<0?(this.selectionStart+=t,this.selectionEnd=this.selectionStart):(this.selectionEnd+=t,this.selectionStart=this.selectionEnd),0!==t}moveCursorLeft(t){0===this.selectionStart&&0===this.selectionEnd||this._moveCursorLeftOrRight("Left",t)}_move(t,e,s){let i;if(t.altKey)i=this["findWordBoundary".concat(s)](this[e]);else{if(!t.metaKey&&35!==t.keyCode&&36!==t.keyCode)return this[e]+="Left"===s?-1:1,!0;i=this["findLineBoundary".concat(s)](this[e])}return void 0!==i&&this[e]!==i&&(this[e]=i,!0)}_moveLeft(t,e){return this._move(t,e,"Left")}_moveRight(t,e){return this._move(t,e,"Right")}moveCursorLeftWithoutShift(t){let e=!0;return this._selectionDirection=M,this.selectionEnd===this.selectionStart&&0!==this.selectionStart&&(e=this._moveLeft(t,"selectionStart")),this.selectionEnd=this.selectionStart,e}moveCursorLeftWithShift(t){return this._selectionDirection===A&&this.selectionStart!==this.selectionEnd?this._moveLeft(t,"selectionEnd"):0!==this.selectionStart?(this._selectionDirection=M,this._moveLeft(t,"selectionStart")):void 0}moveCursorRight(t){this.selectionStart>=this._text.length&&this.selectionEnd>=this._text.length||this._moveCursorLeftOrRight("Right",t)}_moveCursorLeftOrRight(t,e){const s="moveCursor".concat(t).concat(e.shiftKey?"WithShift":"WithoutShift");this._currentCursorOpacity=1,this[s](e)&&(this.abortCursorAnimation(),this.initDelayedCursor(),this._fireSelectionChanged(),this._updateTextarea())}moveCursorRightWithShift(t){return this._selectionDirection===M&&this.selectionStart!==this.selectionEnd?this._moveRight(t,"selectionStart"):this.selectionEnd!==this._text.length?(this._selectionDirection=A,this._moveRight(t,"selectionEnd")):void 0}moveCursorRightWithoutShift(t){let e=!0;return this._selectionDirection=A,this.selectionStart===this.selectionEnd?(e=this._moveRight(t,"selectionStart"),this.selectionEnd=this.selectionStart):this.selectionStart=this.selectionEnd,e}}const Io=t=>!!t.button;class Bo extends Ro{constructor(){super(...arguments),t(this,"draggableTextDelegate",void 0)}initBehavior(){this.on("mousedown",this._mouseDownHandler),this.on("mouseup",this.mouseUpHandler),this.on("mousedblclick",this.doubleClickHandler),this.on("mousetripleclick",this.tripleClickHandler),this.draggableTextDelegate=new jo(this),super.initBehavior()}shouldStartDragging(){return this.draggableTextDelegate.isActive()}onDragStart(t){return this.draggableTextDelegate.onDragStart(t)}canDrop(t){return this.draggableTextDelegate.canDrop(t)}doubleClickHandler(t){this.isEditing&&(this.selectWord(this.getSelectionStartFromPointer(t.e)),this.renderCursorOrSelection())}tripleClickHandler(t){this.isEditing&&(this.selectLine(this.getSelectionStartFromPointer(t.e)),this.renderCursorOrSelection())}_mouseDownHandler(t){let{e:e,alreadySelected:s}=t;this.canvas&&this.editable&&!Io(e)&&!this.getActiveControl()&&(this.draggableTextDelegate.start(e)||(this.canvas.textEditingManager.register(this),s&&(this.inCompositionMode=!1,this.setCursorByClick(e)),this.isEditing&&(this.__selectionStartOnMouseDown=this.selectionStart,this.selectionStart===this.selectionEnd&&this.abortCursorAnimation(),this.renderCursorOrSelection()),this.selected||(this.selected=s||this.isEditing)))}mouseUpHandler(t){let{e:e,transform:s}=t;const i=this.draggableTextDelegate.end(e);if(this.canvas){this.canvas.textEditingManager.unregister(this);const t=this.canvas._activeObject;if(t&&t!==this)return}!this.editable||this.group&&!this.group.interactive||s&&s.actionPerformed||Io(e)||i||this.selected&&!this.getActiveControl()&&(this.enterEditing(e),this.selectionStart===this.selectionEnd?this.initDelayedCursor(!0):this.renderCursorOrSelection())}setCursorByClick(t){const e=this.getSelectionStartFromPointer(t),s=this.selectionStart,i=this.selectionEnd;t.shiftKey?this.setSelectionStartEndWithShift(s,i,e):(this.selectionStart=e,this.selectionEnd=e),this.isEditing&&(this._fireSelectionChanged(),this._updateTextarea())}getSelectionStartFromPointer(t){const e=this.canvas.getScenePoint(t).transform(wt(this.calcTransformMatrix())).add(new ot(-this._getLeftOffset(),-this._getTopOffset()));let s=0,i=0,r=0;for(let t=0;t<this._textLines.length&&s<=e.y;t++)s+=this.getHeightOfLine(t),r=t,t>0&&(i+=this._textLines[t-1].length+this.missingNewlineOffset(t-1));let n=Math.abs(this._getLineLeftOffset(r));const o=this._textLines[r].length,a=this.__charBounds[r];for(let t=0;t<o;t++){const s=n+a[t].kernedWidth;if(e.x<=s){Math.abs(e.x-s)<=Math.abs(e.x-n)&&i++;break}n=s,i++}return Math.min(this.flipX?o-i:i,this._text.length)}}const Xo="moveCursorUp",Yo="moveCursorDown",Wo="moveCursorLeft",Vo="moveCursorRight",zo="exitEditing",Go=(t,e)=>{const s=e.getRetinaScaling();t.setTransform(s,0,0,s,0,0);const i=e.viewportTransform;t.transform(i[0],i[1],i[2],i[3],i[4],i[5])},Ho=s({selectionStart:0,selectionEnd:0,selectionColor:"rgba(17,119,255,0.3)",isEditing:!1,editable:!0,editingBorderColor:"rgba(102,153,255,0.25)",cursorWidth:2,cursorColor:"",cursorDelay:1e3,cursorDuration:600,caching:!0,hiddenTextareaContainer:null,keysMap:{9:zo,27:zo,33:Xo,34:Yo,35:Vo,36:Wo,37:Wo,38:Xo,39:Vo,40:Yo},keysMapRtl:{9:zo,27:zo,33:Xo,34:Yo,35:Wo,36:Vo,37:Vo,38:Xo,39:Wo,40:Yo},ctrlKeysMapDown:{65:"cmdAll"},ctrlKeysMapUp:{67:"copy",88:"cut"}},{_selectionDirection:null,_reSpace:/\s|\r?\n/,inCompositionMode:!1});class No extends Bo{static getDefaults(){return s(s({},super.getDefaults()),No.ownDefaults)}get type(){const t=super.type;return"itext"===t?"i-text":t}constructor(t,e){super(t,s(s({},No.ownDefaults),e)),this.initBehavior()}_set(t,e){return this.isEditing&&this._savedProps&&t in this._savedProps?(this._savedProps[t]=e,this):("canvas"===t&&(this.canvas instanceof Xn&&this.canvas.textEditingManager.remove(this),e instanceof Xn&&e.textEditingManager.add(this)),super._set(t,e))}setSelectionStart(t){t=Math.max(t,0),this._updateAndFire("selectionStart",t)}setSelectionEnd(t){t=Math.min(t,this.text.length),this._updateAndFire("selectionEnd",t)}_updateAndFire(t,e){this[t]!==e&&(this._fireSelectionChanged(),this[t]=e),this._updateTextarea()}_fireSelectionChanged(){this.fire("selection:changed"),this.canvas&&this.canvas.fire("text:selection:changed",{target:this})}initDimensions(){this.isEditing&&this.initDelayedCursor(),super.initDimensions()}getSelectionStyles(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:this.selectionStart||0,e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:this.selectionEnd,s=arguments.length>2?arguments[2]:void 0;return super.getSelectionStyles(t,e,s)}setSelectionStyles(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:this.selectionStart||0,s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:this.selectionEnd;return super.setSelectionStyles(t,e,s)}get2DCursorLocation(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:this.selectionStart,e=arguments.length>1?arguments[1]:void 0;return super.get2DCursorLocation(t,e)}render(t){super.render(t),this.cursorOffsetCache={},this.renderCursorOrSelection()}toCanvasElement(t){const e=this.isEditing;this.isEditing=!1;const s=super.toCanvasElement(t);return this.isEditing=e,s}renderCursorOrSelection(){if(!this.isEditing||!this.canvas)return;const t=this.clearContextTop(!0);if(!t)return;const e=this._getCursorBoundaries(),s=this.findAncestorsWithClipPath(),i=s.length>0;let r,n=t;if(i){r=vt(t.canvas),n=r.getContext("2d"),Go(n,this.canvas);const e=this.calcTransformMatrix();n.transform(e[0],e[1],e[2],e[3],e[4],e[5])}if(this.selectionStart!==this.selectionEnd||this.inCompositionMode?this.renderSelection(n,e):this.renderCursor(n,e),i)for(const e of s){const s=e.clipPath,i=vt(t.canvas),r=i.getContext("2d");if(Go(r,this.canvas),!s.absolutePositioned){const t=e.calcTransformMatrix();r.transform(t[0],t[1],t[2],t[3],t[4],t[5])}s.transform(r),s.drawObject(r,!0,{}),this.drawClipPathOnCache(n,s,i)}i&&(t.setTransform(1,0,0,1,0,0),t.drawImage(r,0,0)),this.canvas.contextTopDirty=!0,t.restore()}findAncestorsWithClipPath(){const t=[];let e=this;for(;e;)e.clipPath&&t.push(e),e=e.parent;return t}_getCursorBoundaries(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:this.selectionStart,e=arguments.length>1?arguments[1]:void 0;const s=this._getLeftOffset(),i=this._getTopOffset(),r=this._getCursorBoundariesOffsets(t,e);return{left:s,top:i,leftOffset:r.left,topOffset:r.top}}_getCursorBoundariesOffsets(t,e){return e?this.__getCursorBoundariesOffsets(t):this.cursorOffsetCache&&"top"in this.cursorOffsetCache?this.cursorOffsetCache:this.cursorOffsetCache=this.__getCursorBoundariesOffsets(t)}__getCursorBoundariesOffsets(t){let e=0,s=0;const{charIndex:i,lineIndex:r}=this.get2DCursorLocation(t);for(let t=0;t<r;t++)e+=this.getHeightOfLine(t);const n=this._getLineLeftOffset(r),o=this.__charBounds[r][i];o&&(s=o.left),0!==this.charSpacing&&i===this._textLines[r].length&&(s-=this._getWidthOfCharSpacing());const a={top:e,left:n+(s>0?s:0)};return"rtl"===this.direction&&(this.textAlign===A||this.textAlign===Je||this.textAlign===Ze?a.left*=-1:this.textAlign===M||this.textAlign===Qe?a.left=n-(s>0?s:0):this.textAlign!==D&&this.textAlign!==$e||(a.left=n-(s>0?s:0))),a}renderCursorAt(t){this._renderCursor(this.canvas.contextTop,this._getCursorBoundaries(t,!0),t)}renderCursor(t,e){this._renderCursor(t,e,this.selectionStart)}getCursorRenderingData(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:this.selectionStart,e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:this._getCursorBoundaries(t);const s=this.get2DCursorLocation(t),i=s.lineIndex,r=s.charIndex>0?s.charIndex-1:0,n=this.getValueOfPropertyAt(i,r,"fontSize"),o=this.getObjectScaling().x*this.canvas.getZoom(),a=this.cursorWidth/o,h=this.getValueOfPropertyAt(i,r,"deltaY"),c=e.topOffset+(1-this._fontSizeFraction)*this.getHeightOfLine(i)/this.lineHeight-n*(1-this._fontSizeFraction);return{color:this.cursorColor||this.getValueOfPropertyAt(i,r,"fill"),opacity:this._currentCursorOpacity,left:e.left+e.leftOffset-a/2,top:c+e.top+h,width:a,height:n}}_renderCursor(t,e,s){const{color:i,opacity:r,left:n,top:o,width:a,height:h}=this.getCursorRenderingData(s,e);t.fillStyle=i,t.globalAlpha=r,t.fillRect(n,o,a,h)}renderSelection(t,e){const s={selectionStart:this.inCompositionMode?this.hiddenTextarea.selectionStart:this.selectionStart,selectionEnd:this.inCompositionMode?this.hiddenTextarea.selectionEnd:this.selectionEnd};this._renderSelection(t,s,e)}renderDragSourceEffect(){const t=this.draggableTextDelegate.getDragStartSelection();this._renderSelection(this.canvas.contextTop,t,this._getCursorBoundaries(t.selectionStart,!0))}renderDropTargetEffect(t){const e=this.getSelectionStartFromPointer(t);this.renderCursorAt(e)}_renderSelection(t,e,s){const i=e.selectionStart,r=e.selectionEnd,n=this.textAlign.includes(Je),o=this.get2DCursorLocation(i),a=this.get2DCursorLocation(r),h=o.lineIndex,c=a.lineIndex,l=o.charIndex<0?0:o.charIndex,u=a.charIndex<0?0:a.charIndex;for(let e=h;e<=c;e++){const i=this._getLineLeftOffset(e)||0;let r=this.getHeightOfLine(e),o=0,a=0,d=0;if(e===h&&(a=this.__charBounds[h][l].left),e>=h&&e<c)d=n&&!this.isEndOfWrapping(e)?this.width:this.getLineWidth(e)||5;else if(e===c)if(0===u)d=this.__charBounds[c][u].left;else{const t=this._getWidthOfCharSpacing();d=this.__charBounds[c][u-1].left+this.__charBounds[c][u-1].width-t}o=r,(this.lineHeight<1||e===c&&this.lineHeight>1)&&(r/=this.lineHeight);let g=s.left+i+a,f=r,p=0;const m=d-a;this.inCompositionMode?(t.fillStyle=this.compositionColor||"black",f=1,p=r):t.fillStyle=this.selectionColor,"rtl"===this.direction&&(this.textAlign===A||this.textAlign===Je||this.textAlign===Ze?g=this.width-g-m:this.textAlign===M||this.textAlign===Qe?g=s.left+i-d:this.textAlign!==D&&this.textAlign!==$e||(g=s.left+i-d)),t.fillRect(g,s.top+s.topOffset+p,m,f),s.topOffset+=o}}getCurrentCharFontSize(){const t=this._getCurrentCharIndex();return this.getValueOfPropertyAt(t.l,t.c,"fontSize")}getCurrentCharColor(){const t=this._getCurrentCharIndex();return this.getValueOfPropertyAt(t.l,t.c,K)}_getCurrentCharIndex(){const t=this.get2DCursorLocation(this.selectionStart,!0),e=t.charIndex>0?t.charIndex-1:0;return{l:t.lineIndex,c:e}}dispose(){this.exitEditingImpl(),this.draggableTextDelegate.dispose(),super.dispose()}}t(No,"ownDefaults",Ho),t(No,"type","IText"),tt.setClass(No),tt.setClass(No,"i-text");class Uo extends No{static getDefaults(){return s(s({},super.getDefaults()),Uo.ownDefaults)}constructor(t,e){super(t,s(s({},Uo.ownDefaults),e))}static createControls(){return{controls:Ai()}}initDimensions(){this.initialized&&(this.isEditing&&this.initDelayedCursor(),this._clearCache(),this.dynamicMinWidth=0,this._styleMap=this._generateStyleMap(this._splitText()),this.dynamicMinWidth>this.width&&this._set("width",this.dynamicMinWidth),this.textAlign.includes(Je)&&this.enlargeSpaces(),this.height=this.calcTextHeight())}_generateStyleMap(t){let e=0,s=0,i=0;const r={};for(let n=0;n<t.graphemeLines.length;n++)"\n"===t.graphemeText[i]&&n>0?(s=0,i++,e++):!this.splitByGrapheme&&this._reSpaceAndTab.test(t.graphemeText[i])&&n>0&&(s++,i++),r[n]={line:e,offset:s},i+=t.graphemeLines[n].length,s+=t.graphemeLines[n].length;return r}styleHas(t,e){if(this._styleMap&&!this.isWrapping){const t=this._styleMap[e];t&&(e=t.line)}return super.styleHas(t,e)}isEmptyStyles(t){if(!this.styles)return!0;let e,s=0,i=t+1,r=!1;const n=this._styleMap[t],o=this._styleMap[t+1];n&&(t=n.line,s=n.offset),o&&(i=o.line,r=i===t,e=o.offset);const a=void 0===t?this.styles:{line:this.styles[t]};for(const t in a)for(const i in a[t]){const n=parseInt(i,10);if(n>=s&&(!r||n<e))for(const e in a[t][i])return!1}return!0}_getStyleDeclaration(t,e){if(this._styleMap&&!this.isWrapping){const s=this._styleMap[t];if(!s)return{};t=s.line,e=s.offset+e}return super._getStyleDeclaration(t,e)}_setStyleDeclaration(t,e,s){const i=this._styleMap[t];super._setStyleDeclaration(i.line,i.offset+e,s)}_deleteStyleDeclaration(t,e){const s=this._styleMap[t];super._deleteStyleDeclaration(s.line,s.offset+e)}_getLineStyle(t){const e=this._styleMap[t];return!!this.styles[e.line]}_setLineStyle(t){const e=this._styleMap[t];super._setLineStyle(e.line)}_wrapText(t,e){this.isWrapping=!0;const s=this.getGraphemeDataForRender(t),i=[];for(let t=0;t<s.wordsData.length;t++)i.push(...this._wrapLine(t,e,s));return this.isWrapping=!1,i}getGraphemeDataForRender(t){const e=this.splitByGrapheme,s=e?"":" ";let i=0;return{wordsData:t.map(((t,r)=>{let n=0;const o=e?this.graphemeSplit(t):this.wordSplit(t);return 0===o.length?[{word:[],width:0}]:o.map((t=>{const o=e?[t]:this.graphemeSplit(t),a=this._measureWord(o,r,n);return i=Math.max(a,i),n+=o.length+s.length,{word:o,width:a}}))})),largestWordWidth:i}}_measureWord(t,e){let s,i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:0,r=0;for(let n=0,o=t.length;n<o;n++){r+=this._getGraphemeBox(t[n],e,n+i,s,true).kernedWidth,s=t[n]}return r}wordSplit(t){return t.split(this._wordJoiners)}_wrapLine(t,e,s){let{largestWordWidth:i,wordsData:r}=s,n=arguments.length>3&&void 0!==arguments[3]?arguments[3]:0;const o=this._getWidthOfCharSpacing(),a=this.splitByGrapheme,h=[],c=a?"":" ";let l=0,u=[],d=0,g=0,f=!0;e-=n;const p=Math.max(e,i,this.dynamicMinWidth),m=r[t];let v;for(d=0,v=0;v<m.length;v++){const{word:e,width:s}=m[v];d+=e.length,l+=g+s-o,l>p&&!f?(h.push(u),u=[],l=s,f=!0):l+=o,f||a||u.push(c),u=u.concat(e),g=a?0:this._measureWord([c],t,d),d++,f=!1}return v&&h.push(u),i+n>this.dynamicMinWidth&&(this.dynamicMinWidth=i-o+n),h}isEndOfWrapping(t){return!this._styleMap[t+1]||this._styleMap[t+1].line!==this._styleMap[t].line}missingNewlineOffset(t,e){return this.splitByGrapheme&&!e?this.isEndOfWrapping(t)?1:0:1}_splitTextIntoLines(t){const e=super._splitTextIntoLines(t),s=this._wrapText(e.lines,this.width),i=new Array(s.length);for(let t=0;t<s.length;t++)i[t]=s[t].join("");return e.lines=i,e.graphemeLines=s,e}getMinWidth(){return Math.max(this.minWidth,this.dynamicMinWidth)}_removeExtraneousStyles(){const t=new Map;for(const e in this._styleMap){const s=parseInt(e,10);if(this._textLines[s]){const s=this._styleMap[e].line;t.set("".concat(s),!0)}}for(const e in this.styles)t.has(e)||delete this.styles[e]}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];return super.toObject(["minWidth","splitByGrapheme",...t])}}t(Uo,"type","Textbox"),t(Uo,"textLayoutProperties",[...No.textLayoutProperties,"width"]),t(Uo,"ownDefaults",{minWidth:20,dynamicMinWidth:2,lockScalingFlip:!0,noScaleCache:!1,_wordJoiners:/[ \t\r]/,splitByGrapheme:!1}),tt.setClass(Uo);class qo extends Xr{shouldPerformLayout(t){return!!t.target.clipPath&&super.shouldPerformLayout(t)}shouldLayoutClipPath(){return!1}calcLayoutResult(t,e){const{target:s}=t,{clipPath:i,group:r}=s;if(!i||!this.shouldPerformLayout(t))return;const{width:n,height:o}=he(Br(s,i)),a=new ot(n,o);if(i.absolutePositioned){return{center:me(i.getRelativeCenterPoint(),void 0,r?r.calcTransformMatrix():void 0),size:a}}{const r=i.getRelativeCenterPoint().transform(s.calcOwnMatrix(),!0);if(this.shouldPerformLayout(t)){const{center:s=new ot,correction:i=new ot}=this.calcBoundingBox(e,t)||{};return{center:s.add(r),correction:i.subtract(r),size:a}}return{center:s.getRelativeCenterPoint().add(r),size:a}}}}t(qo,"type","clip-path"),tt.setClass(qo);class Ko extends Xr{getInitialSize(t,e){let{target:s}=t,{size:i}=e;return new ot(s.width||i.x,s.height||i.y)}}t(Ko,"type","fixed"),tt.setClass(Ko);class Jo extends Gr{subscribeTargets(t){const e=t.target;t.targets.reduce(((t,e)=>(e.parent&&t.add(e.parent),t)),new Set).forEach((t=>{t.layoutManager.subscribeTargets({target:t,targets:[e]})}))}unsubscribeTargets(t){const e=t.target,s=e.getObjects();t.targets.reduce(((t,e)=>(e.parent&&t.add(e.parent),t)),new Set).forEach((t=>{!s.some((e=>e.parent===t))&&t.layoutManager.unsubscribeTargets({target:t,targets:[e]})}))}}class Qo extends Ur{static getDefaults(){return s(s({},super.getDefaults()),Qo.ownDefaults)}constructor(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[],e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};super(),Object.assign(this,Qo.ownDefaults),this.setOptions(e);const{left:s,top:i,layoutManager:r}=e;this.groupInit(t,{left:s,top:i,layoutManager:null!=r?r:new Jo})}_shouldSetNestedCoords(){return!0}__objectSelectionMonitor(){}multiSelectAdd(){for(var t=arguments.length,e=new Array(t),s=0;s<t;s++)e[s]=arguments[s];"selection-order"===this.multiSelectionStacking?this.add(...e):e.forEach((t=>{const e=this._objects.findIndex((e=>e.isInFrontOf(t))),s=-1===e?this.size():e;this.insertAt(s,t)}))}canEnterGroup(t){return this.getObjects().some((e=>e.isDescendantOf(t)||t.isDescendantOf(e)))?(a("error","ActiveSelection: circular object trees are not supported, this call has no effect"),!1):super.canEnterGroup(t)}enterGroup(t,e){t.parent&&t.parent===t.group?t.parent._exitGroup(t):t.group&&t.parent!==t.group&&t.group.remove(t),this._enterGroup(t,e)}exitGroup(t,e){this._exitGroup(t,e),t.parent&&t.parent._enterGroup(t,!0)}_onAfterObjectsChange(t,e){super._onAfterObjectsChange(t,e);const s=new Set;e.forEach((t=>{const{parent:e}=t;e&&s.add(e)})),t===Rr?s.forEach((t=>{t._onAfterObjectsChange(Lr,e)})):s.forEach((t=>{t._set("dirty",!0)}))}onDeselect(){return this.removeAll(),!1}toString(){return"#<ActiveSelection: (".concat(this.complexity(),")>")}shouldCache(){return!1}isOnACache(){return!1}_renderControls(t,e,i){t.save(),t.globalAlpha=this.isMoving?this.borderOpacityWhenMoving:1;const r=s(s({hasControls:!1},i),{},{forActiveSelection:!0});for(let e=0;e<this._objects.length;e++)this._objects[e]._renderControls(t,r);super._renderControls(t,e),t.restore()}}t(Qo,"type","ActiveSelection"),t(Qo,"ownDefaults",{multiSelectionStacking:"canvas-stacking"}),tt.setClass(Qo),tt.setClass(Qo,"activeSelection");class Zo{constructor(){t(this,"resources",{})}applyFilters(t,e,s,i,r){const n=r.getContext("2d");if(!n)return;n.drawImage(e,0,0,s,i);const o={sourceWidth:s,sourceHeight:i,imageData:n.getImageData(0,0,s,i),originalEl:e,originalImageData:n.getImageData(0,0,s,i),canvasEl:r,ctx:n,filterBackend:this};t.forEach((t=>{t.applyTo(o)}));const{imageData:a}=o;return a.width===s&&a.height===i||(r.width=a.width,r.height=a.height),n.putImageData(a,0,0),o}}class $o{constructor(){let{tileSize:e=o.textureSize}=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};t(this,"aPosition",new Float32Array([0,0,0,1,1,0,1,1])),t(this,"resources",{}),this.tileSize=e,this.setupGLContext(e,e),this.captureGPUInfo()}setupGLContext(t,e){this.dispose(),this.createWebGLCanvas(t,e)}createWebGLCanvas(t,e){const s=vt({width:t,height:e}),i=s.getContext("webgl",{alpha:!0,premultipliedAlpha:!1,depth:!1,stencil:!1,antialias:!1});i&&(i.clearColor(0,0,0,0),this.canvas=s,this.gl=i)}applyFilters(t,e,s,i,r,n){const o=this.gl,a=r.getContext("2d");if(!o||!a)return;let h;n&&(h=this.getCachedTexture(n,e));const c={originalWidth:e.width||e.naturalWidth||0,originalHeight:e.height||e.naturalHeight||0,sourceWidth:s,sourceHeight:i,destinationWidth:s,destinationHeight:i,context:o,sourceTexture:this.createTexture(o,s,i,h?void 0:e),targetTexture:this.createTexture(o,s,i),originalTexture:h||this.createTexture(o,s,i,h?void 0:e),passes:t.length,webgl:!0,aPosition:this.aPosition,programCache:this.programCache,pass:0,filterBackend:this,targetCanvas:r},l=o.createFramebuffer();return o.bindFramebuffer(o.FRAMEBUFFER,l),t.forEach((t=>{t&&t.applyTo(c)})),function(t){const e=t.targetCanvas,s=e.width,i=e.height,r=t.destinationWidth,n=t.destinationHeight;s===r&&i===n||(e.width=r,e.height=n)}(c),this.copyGLTo2D(o,c),o.bindTexture(o.TEXTURE_2D,null),o.deleteTexture(c.sourceTexture),o.deleteTexture(c.targetTexture),o.deleteFramebuffer(l),a.setTransform(1,0,0,1,0,0),c}dispose(){this.canvas&&(this.canvas=null,this.gl=null),this.clearWebGLCaches()}clearWebGLCaches(){this.programCache={},this.textureCache={}}createTexture(t,e,s,i,r){const{NEAREST:n,TEXTURE_2D:o,RGBA:a,UNSIGNED_BYTE:h,CLAMP_TO_EDGE:c,TEXTURE_MAG_FILTER:l,TEXTURE_MIN_FILTER:u,TEXTURE_WRAP_S:d,TEXTURE_WRAP_T:g}=t,f=t.createTexture();return t.bindTexture(o,f),t.texParameteri(o,l,r||n),t.texParameteri(o,u,r||n),t.texParameteri(o,d,c),t.texParameteri(o,g,c),i?t.texImage2D(o,0,a,a,h,i):t.texImage2D(o,0,a,e,s,0,a,h,null),f}getCachedTexture(t,e,s){const{textureCache:i}=this;if(i[t])return i[t];{const r=this.createTexture(this.gl,e.width,e.height,e,s);return r&&(i[t]=r),r}}evictCachesForKey(t){this.textureCache[t]&&(this.gl.deleteTexture(this.textureCache[t]),delete this.textureCache[t])}copyGLTo2D(t,e){const s=t.canvas,i=e.targetCanvas,r=i.getContext("2d");if(!r)return;r.translate(0,i.height),r.scale(1,-1);const n=s.height-i.height;r.drawImage(s,0,n,i.width,i.height,0,0,i.width,i.height)}copyGLTo2DPutImageData(t,e){const s=e.targetCanvas.getContext("2d"),i=e.destinationWidth,r=e.destinationHeight,n=i*r*4;if(!s)return;const o=new Uint8Array(this.imageBuffer,0,n),a=new Uint8ClampedArray(this.imageBuffer,0,n);t.readPixels(0,0,i,r,t.RGBA,t.UNSIGNED_BYTE,o);const h=new ImageData(a,i,r);s.putImageData(h,0,0)}captureGPUInfo(){if(this.gpuInfo)return this.gpuInfo;const t=this.gl,e={renderer:"",vendor:""};if(!t)return e;const s=t.getExtension("WEBGL_debug_renderer_info");if(s){const i=t.getParameter(s.UNMASKED_RENDERER_WEBGL),r=t.getParameter(s.UNMASKED_VENDOR_WEBGL);i&&(e.renderer=i.toLowerCase()),r&&(e.vendor=r.toLowerCase())}return this.gpuInfo=e,e}}let ta;function ea(){const{WebGLProbe:t}=p();return t.queryWebGL(pt()),o.enableGLFiltering&&t.isSupported(o.textureSize)?new $o({tileSize:o.textureSize}):new Zo}function sa(){return!ta&&(!(arguments.length>0&&void 0!==arguments[0])||arguments[0])&&(ta=ea()),ta}function ia(t){ta=t}const ra=["filters","resizeFilter","src","crossOrigin","type"],na=["cropX","cropY"];class oa extends Li{static getDefaults(){return s(s({},super.getDefaults()),oa.ownDefaults)}constructor(e,s){super(),t(this,"_lastScaleX",1),t(this,"_lastScaleY",1),t(this,"_filterScalingX",1),t(this,"_filterScalingY",1),this.filters=[],Object.assign(this,oa.ownDefaults),this.setOptions(s),this.cacheKey="texture".concat(ft()),this.setElement("string"==typeof e?(this.canvas&&Kt(this.canvas.getElement())||m()).getElementById(e):e,s)}getElement(){return this._element}setElement(t){var e;let s=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};this.removeTexture(this.cacheKey),this.removeTexture("".concat(this.cacheKey,"_filtered")),this._element=t,this._originalElement=t,this._setWidthHeight(s),null===(e=t.classList)||void 0===e||e.add(oa.CSS_CANVAS),0!==this.filters.length&&this.applyFilters(),this.resizeFilter&&this.applyResizeFilters()}removeTexture(t){const e=sa(!1);e instanceof $o&&e.evictCachesForKey(t)}dispose(){super.dispose(),this.removeTexture(this.cacheKey),this.removeTexture("".concat(this.cacheKey,"_filtered")),this._cacheContext=null,["_originalElement","_element","_filteredEl","_cacheCanvas"].forEach((t=>{const e=this[t];e&&p().dispose(e),this[t]=void 0}))}getCrossOrigin(){return this._originalElement&&(this._originalElement.crossOrigin||null)}getOriginalSize(){const t=this.getElement();return t?{width:t.naturalWidth||t.width,height:t.naturalHeight||t.height}:{width:0,height:0}}_stroke(t){if(!this.stroke||0===this.strokeWidth)return;const e=this.width/2,s=this.height/2;t.beginPath(),t.moveTo(-e,-s),t.lineTo(e,-s),t.lineTo(e,s),t.lineTo(-e,s),t.lineTo(-e,-s),t.closePath()}toObject(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:[];const e=[];return this.filters.forEach((t=>{t&&e.push(t.toObject())})),s(s({},super.toObject([...na,...t])),{},{src:this.getSrc(),crossOrigin:this.getCrossOrigin(),filters:e},this.resizeFilter?{resizeFilter:this.resizeFilter.toObject()}:{})}hasCrop(){return!!this.cropX||!!this.cropY||this.width<this._element.width||this.height<this._element.height}_toSVG(){const t=[],e=this._element,s=-this.width/2,i=-this.height/2;let r=[],n=[],o="",a="";if(!e)return[];if(this.hasCrop()){const t=ft();r.push('<clipPath id="imageCrop_'+t+'">\n','\t<rect x="'+s+'" y="'+i+'" width="'+this.width+'" height="'+this.height+'" />\n',"</clipPath>\n"),o=' clip-path="url(#imageCrop_'+t+')" '}if(this.imageSmoothing||(a=' image-rendering="optimizeSpeed"'),t.push("\t<image ","COMMON_PARTS",'xlink:href="'.concat(this.getSvgSrc(!0),'" x="').concat(s-this.cropX,'" y="').concat(i-this.cropY,'" width="').concat(e.width||e.naturalWidth,'" height="').concat(e.height||e.naturalHeight,'"').concat(a).concat(o,"></image>\n")),this.stroke||this.strokeDashArray){const t=this.fill;this.fill=null,n=['\t<rect x="'.concat(s,'" y="').concat(i,'" width="').concat(this.width,'" height="').concat(this.height,'" style="').concat(this.getSvgStyles(),'" />\n')],this.fill=t}return r=this.paintFirst!==K?r.concat(n,t):r.concat(t,n),r}getSrc(t){const e=t?this._element:this._originalElement;return e?e.toDataURL?e.toDataURL():this.srcFromAttribute?e.getAttribute("src")||"":e.src:this.src||""}getSvgSrc(t){return this.getSrc(t)}setSrc(t){let{crossOrigin:e,signal:s}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};return It(t,{crossOrigin:e,signal:s}).then((t=>{void 0!==e&&this.set({crossOrigin:e}),this.setElement(t)}))}toString(){return'#<Image: { src: "'.concat(this.getSrc(),'" }>')}applyResizeFilters(){const t=this.resizeFilter,e=this.minimumScaleTrigger,s=this.getTotalObjectScaling(),i=s.x,r=s.y,n=this._filteredEl||this._originalElement;if(this.group&&this.set("dirty",!0),!t||i>e&&r>e)return this._element=n,this._filterScalingX=1,this._filterScalingY=1,this._lastScaleX=i,void(this._lastScaleY=r);const o=vt(n),{width:a,height:h}=n;this._element=o,this._lastScaleX=t.scaleX=i,this._lastScaleY=t.scaleY=r,sa().applyFilters([t],n,a,h,this._element),this._filterScalingX=o.width/this._originalElement.width,this._filterScalingY=o.height/this._originalElement.height}applyFilters(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:this.filters||[];if(t=t.filter((t=>t&&!t.isNeutralState())),this.set("dirty",!0),this.removeTexture("".concat(this.cacheKey,"_filtered")),0===t.length)return this._element=this._originalElement,this._filteredEl=void 0,this._filterScalingX=1,void(this._filterScalingY=1);const e=this._originalElement,s=e.naturalWidth||e.width,i=e.naturalHeight||e.height;if(this._element===this._originalElement){const t=vt({width:s,height:i});this._element=t,this._filteredEl=t}else this._filteredEl&&(this._element=this._filteredEl,this._filteredEl.getContext("2d").clearRect(0,0,s,i),this._lastScaleX=1,this._lastScaleY=1);sa().applyFilters(t,this._originalElement,s,i,this._element,this.cacheKey),this._originalElement.width===this._element.width&&this._originalElement.height===this._element.height||(this._filterScalingX=this._element.width/this._originalElement.width,this._filterScalingY=this._element.height/this._originalElement.height)}_render(t){t.imageSmoothingEnabled=this.imageSmoothing,!0!==this.isMoving&&this.resizeFilter&&this._needsResize()&&this.applyResizeFilters(),this._stroke(t),this._renderPaintInOrder(t)}drawCacheOnCanvas(t){t.imageSmoothingEnabled=this.imageSmoothing,super.drawCacheOnCanvas(t)}shouldCache(){return this.needsItsOwnCache()}_renderFill(t){const e=this._element;if(!e)return;const s=this._filterScalingX,i=this._filterScalingY,r=this.width,n=this.height,o=Math.max(this.cropX,0),a=Math.max(this.cropY,0),h=e.naturalWidth||e.width,c=e.naturalHeight||e.height,l=o*s,u=a*i,d=Math.min(r*s,h-l),g=Math.min(n*i,c-u),f=-r/2,p=-n/2,m=Math.min(r,h/s-o),v=Math.min(n,c/i-a);e&&t.drawImage(e,l,u,d,g,f,p,m,v)}_needsResize(){const t=this.getTotalObjectScaling();return t.x!==this._lastScaleX||t.y!==this._lastScaleY}_resetWidthHeight(){this.set(this.getOriginalSize())}_setWidthHeight(){let{width:t,height:e}=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};const s=this.getOriginalSize();this.width=t||s.width,this.height=e||s.height}parsePreserveAspectRatioAttribute(){const t=Xe(this.preserveAspectRatio||""),e=this.width,s=this.height,i={width:e,height:s};let r,n=this._element.width,o=this._element.height,a=1,h=1,c=0,l=0,u=0,d=0;return!t||t.alignX===j&&t.alignY===j?(a=e/n,h=s/o):("meet"===t.meetOrSlice&&(a=h=qr(this._element,i),r=(e-n*a)/2,"Min"===t.alignX&&(c=-r),"Max"===t.alignX&&(c=r),r=(s-o*h)/2,"Min"===t.alignY&&(l=-r),"Max"===t.alignY&&(l=r)),"slice"===t.meetOrSlice&&(a=h=Kr(this._element,i),r=n-e/a,"Mid"===t.alignX&&(u=r/2),"Max"===t.alignX&&(u=r),r=o-s/h,"Mid"===t.alignY&&(d=r/2),"Max"===t.alignY&&(d=r),n=e/a,o=s/h)),{width:n,height:o,scaleX:a,scaleY:h,offsetLeft:c,offsetTop:l,cropX:u,cropY:d}}static fromObject(t,e){let{filters:r,resizeFilter:n,src:o,crossOrigin:a,type:h}=t,c=i(t,ra);return Promise.all([It(o,s(s({},e),{},{crossOrigin:a})),r&&Bt(r,e),n&&Bt([n],e),Xt(c,e)]).then((t=>{let[e,i=[],[r]=[],n={}]=t;return new this(e,s(s({},c),{},{src:o,filters:i,resizeFilter:r},n))}))}static fromURL(t){let{crossOrigin:e=null,signal:s}=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},i=arguments.length>2?arguments[2]:void 0;return It(t,{crossOrigin:e,signal:s}).then((t=>new this(t,i)))}static async fromElement(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},s=arguments.length>2?arguments[2]:void 0;const i=Pr(t,this.ATTRIBUTE_NAMES,s);return this.fromURL(i["xlink:href"]||i.href,e,i).catch((t=>(a("log","Unable to parse Image",t),null)))}}function aa(t){if(!us.test(t.nodeName))return{};const e=t.getAttribute("viewBox");let s,i,r=1,n=1,o=0,a=0;const h=t.getAttribute("width"),c=t.getAttribute("height"),l=t.getAttribute("x")||0,u=t.getAttribute("y")||0,d=!(e&&gs.test(e)),g=!h||!c||"100%"===h||"100%"===c;let f="",p=0,m=0;if(d&&(l||u)&&t.parentNode&&"#document"!==t.parentNode.nodeName&&(f=" translate("+Be(l||"0")+" "+Be(u||"0")+") ",s=(t.getAttribute("transform")||"")+f,t.setAttribute("transform",s),t.removeAttribute("x"),t.removeAttribute("y")),d&&g)return{width:0,height:0};const v={width:0,height:0};if(d)return v.width=Be(h),v.height=Be(c),v;const y=e.match(gs);o=-parseFloat(y[1]),a=-parseFloat(y[2]);const _=parseFloat(y[3]),x=parseFloat(y[4]);v.minX=o,v.minY=a,v.viewBoxWidth=_,v.viewBoxHeight=x,g?(v.width=_,v.height=x):(v.width=Be(h),v.height=Be(c),r=v.width/_,n=v.height/x);const C=Xe(t.getAttribute("preserveAspectRatio")||"");if(C.alignX!==j&&("meet"===C.meetOrSlice&&(n=r=r>n?n:r),"slice"===C.meetOrSlice&&(n=r=r>n?r:n),p=v.width-_*r,m=v.height-x*r,"Mid"===C.alignX&&(p/=2),"Mid"===C.alignY&&(m/=2),"Min"===C.alignX&&(p=0),"Min"===C.alignY&&(m=0)),1===r&&1===n&&0===o&&0===a&&0===l&&0===u)return v;if((l||u)&&"#document"!==t.parentNode.nodeName&&(f=" translate("+Be(l||"0")+" "+Be(u||"0")+") "),s=f+" matrix("+r+" 0 0 "+n+" "+(o*r+p)+" "+(a*n+m)+") ","svg"===t.nodeName){for(i=t.ownerDocument.createElementNS(ns,"g");t.firstChild;)i.appendChild(t.firstChild);t.appendChild(i)}else i=t,i.removeAttribute("x"),i.removeAttribute("y"),s=i.getAttribute("transform")+s;return i.setAttribute("transform",s),v}t(oa,"type","Image"),t(oa,"cacheProperties",[...Es,...na]),t(oa,"ownDefaults",{strokeWidth:0,srcFromAttribute:!1,minimumScaleTrigger:.5,cropX:0,cropY:0,imageSmoothing:!0}),t(oa,"CSS_CANVAS","canvas-img"),t(oa,"ATTRIBUTE_NAMES",[...Zi,"x","y","width","height","preserveAspectRatio","xlink:href","href","crossOrigin","image-rendering"]),tt.setClass(oa),tt.setSVGClass(oa);const ha=t=>t.tagName.replace("svg:",""),ca=Ve(["pattern","defs","symbol","metadata","clipPath","mask","desc"]);function la(t,e){let s,i,r,n,o=[];for(r=0,n=e.length;r<n;r++)s=e[r],i=t.getElementsByTagNameNS("http://www.w3.org/2000/svg",s),o=o.concat(Array.from(i));return o}const ua=["gradientTransform","x1","x2","y1","y2","gradientUnits","cx","cy","r","fx","fy"],da="xlink:href";function ga(t,e){var s;const i=(null===(s=e.getAttribute(da))||void 0===s?void 0:s.slice(1))||"",r=t.getElementById(i);if(r&&r.getAttribute(da)&&ga(t,r),r&&(ua.forEach((t=>{const s=r.getAttribute(t);!e.hasAttribute(t)&&s&&e.setAttribute(t,s)})),!e.children.length)){const t=r.cloneNode(!0);for(;t.firstChild;)e.appendChild(t.firstChild)}e.removeAttribute(da)}const fa=["linearGradient","radialGradient","svg:linearGradient","svg:radialGradient"];function pa(t){const e=t.getElementsByTagName("style"),i={};for(let t=0;t<e.length;t++){const r=(e[t].textContent||"").replace(/\/\*[\s\S]*?\*\//g,"");""!==r.trim()&&r.split("}").filter(((t,e,s)=>s.length>1&&t.trim())).forEach((t=>{if((t.match(/{/g)||[]).length>1&&t.trim().startsWith("@"))return;const e=t.split("{"),r={},n=e[1].trim().split(";").filter((function(t){return t.trim()}));for(let t=0;t<n.length;t++){const e=n[t].split(":"),s=e[0].trim(),i=e[1].trim();r[s]=i}(t=e[0].trim()).split(",").forEach((t=>{""!==(t=t.replace(/^svg/i,"").trim())&&(i[t]=s(s({},i[t]||{}),r))}))}))}return i}const ma=t=>tt.getSVGClass(ha(t).toLowerCase());class va{constructor(t,e,s,i,r){this.elements=t,this.options=e,this.reviver=s,this.regexUrl=/^url\(['"]?#([^'"]+)['"]?\)/g,this.doc=i,this.clipPaths=r,this.gradientDefs=function(t){const e=la(t,fa),s={};let i=e.length;for(;i--;){const r=e[i];r.getAttribute("xlink:href")&&ga(t,r);const n=r.getAttribute("id");n&&(s[n]=r)}return s}(i),this.cssRules=pa(i)}parse(){return Promise.all(this.elements.map((t=>this.createObject(t))))}async createObject(t){const e=ma(t);if(e){const s=await e.fromElement(t,this.options,this.cssRules);return this.resolveGradient(s,t,K),this.resolveGradient(s,t,J),s instanceof oa&&s._originalElement?Dn(s,s.parsePreserveAspectRatioAttribute()):Dn(s),await this.resolveClipPath(s,t),this.reviver&&this.reviver(t,s),s}return null}extractPropertyDefinition(t,e,s){const i=t[e],r=this.regexUrl;if(!r.test(i))return;r.lastIndex=0;const n=r.exec(i)[1];return r.lastIndex=0,s[n]}resolveGradient(t,e,i){const r=this.extractPropertyDefinition(t,i,this.gradientDefs);if(r){const n=e.getAttribute(i+"-opacity"),o=$n.fromElement(r,t,s(s({},this.options),{},{opacity:n}));t.set(i,o)}}async resolveClipPath(t,e,s){const i=this.extractPropertyDefinition(t,"clipPath",this.clipPaths);if(i){const r=wt(t.calcTransformMatrix()),n=i[0].parentElement;let o=e;for(;!s&&o.parentElement&&o.getAttribute("clip-path")!==t.clipPath;)o=o.parentElement;o.parentElement.appendChild(n);const a=wr("".concat(o.getAttribute("transform")||""," ").concat(n.getAttribute("originalTransform")||""));n.setAttribute("transform","matrix(".concat(a.join(","),")"));const h=await Promise.all(i.map((t=>ma(t).fromElement(t,this.options,this.cssRules).then((t=>(Dn(t),t.fillRule=t.clipRule,delete t.clipRule,t)))))),c=1===h.length?h[0]:new Ur(h),l=Tt(r,c.calcTransformMatrix());c.clipPath&&await this.resolveClipPath(c,o,n.getAttribute("clip-path")?o:void 0);const{scaleX:u,scaleY:d,angle:g,skewX:f,translateX:p,translateY:m}=Dt(l);c.set({flipX:!1,flipY:!1}),c.set({scaleX:u,scaleY:d,angle:g,skewX:f,skewY:0}),c.setPositionByOrigin(new ot(p,m),D,D),t.clipPath=c}else delete t.clipPath}}const ya=t=>ls.test(ha(t)),_a=()=>({objects:[],elements:[],options:{},allElements:[]});async function xa(t,e){let{crossOrigin:i,signal:r}=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{};if(r&&r.aborted)return a("log",new c("parseSVGDocument")),_a();const n=t.documentElement;!function(t){const e=la(t,["use","svg:use"]),s=["x","y","xlink:href","href","transform"];for(const i of e){const e=i.attributes,r={};for(const t of e)t.value&&(r[t.name]=t.value);const n=(r["xlink:href"]||r.href||"").slice(1);if(""===n)return;const o=t.getElementById(n);if(null===o)return;let a=o.cloneNode(!0);const h=a.attributes,c={};for(const t of h)t.value&&(c[t.name]=t.value);const{x:l=0,y:u=0,transform:d=""}=r,g="".concat(d," ").concat(c.transform||""," translate(").concat(l,", ").concat(u,")");if(aa(a),/^svg$/i.test(a.nodeName)){const t=a.ownerDocument.createElementNS(ns,"g");Object.entries(c).forEach((e=>{let[s,i]=e;return t.setAttributeNS(ns,s,i)})),t.append(...a.childNodes),a=t}for(const t of e){if(!t)continue;const{name:e,value:i}=t;if(!s.includes(e))if("style"===e){const t={};kr(i,t),Object.entries(c).forEach((e=>{let[s,i]=e;t[s]=i})),kr(c.style||"",t);const s=Object.entries(t).map((t=>t.join(":"))).join(";");a.setAttribute(e,s)}else!c[e]&&a.setAttribute(e,i)}a.setAttribute("transform",g),a.setAttribute("instantiated_by_use","1"),a.removeAttribute("id"),i.parentNode.replaceChild(a,i)}}(t);const o=Array.from(n.getElementsByTagName("*")),h=s(s({},aa(n)),{},{crossOrigin:i,signal:r}),l=o.filter((t=>(aa(t),ya(t)&&!function(t){let e=t;for(;e&&(e=e.parentElement);)if(e&&e.nodeName&&ca.test(ha(e))&&!e.getAttribute("instantiated_by_use"))return!0;return!1}(t))));if(!l||l&&!l.length)return s(s({},_a()),{},{options:h,allElements:o});const u={};o.filter((t=>"clipPath"===ha(t))).forEach((t=>{t.setAttribute("originalTransform",t.getAttribute("transform")||"");const e=t.getAttribute("id");u[e]=Array.from(t.getElementsByTagName("*")).filter((t=>ya(t)))}));const d=new va(l,h,e,t,u);return{objects:await d.parse(),elements:l,options:h,allElements:o}}function Ca(t,e,s){return xa((new(v().DOMParser)).parseFromString(t.trim(),"text/xml"),e,s)}function ba(t,e){let s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{};return new Promise(((e,i)=>{kn(t.replace(/^\n\s*/,"").trim(),{onComplete:t=>{const s=t.responseXML;s&&e(s),i()},signal:s.signal})})).then((t=>xa(t,e,s))).catch((()=>_a()))}const Sa=W,wa=t=>function(e,s,i){const{points:r,pathOffset:n}=i;return new ot(r[t]).subtract(n).transform(Tt(i.getViewportTransform(),i.calcTransformMatrix()))},Ta=(t,e,s,i)=>{const{target:r,pointIndex:n}=e,o=r,a=me(new ot(s,i),void 0,o.calcOwnMatrix());return o.points[n]=a.add(o.pathOffset),o.setDimensions(),o.set("dirty",!0),!0},Oa=(t,e)=>function(i,r,n,o){const a=r.target,h=new ot(a.points[(t>0?t:a.points.length)-1]),c=h.subtract(a.pathOffset).transform(a.calcOwnMatrix()),l=e(i,s(s({},r),{},{pointIndex:t}),n,o),u=h.subtract(a.pathOffset).transform(a.calcOwnMatrix()).subtract(c);return a.left-=u.x,a.top-=u.y,l},ka=t=>si(Sa,Oa(t,Ta));const Da=(t,e,s)=>{const{path:i,pathOffset:r}=t,n=i[e];return new ot(n[s]-r.x,n[s+1]-r.y).transform(Tt(t.getViewportTransform(),t.calcTransformMatrix()))};function Ma(t,e,s){const{commandIndex:i,pointIndex:r}=this;return Da(s,i,r)}function Pa(t,e,i,r){const{target:n}=e,{commandIndex:o,pointIndex:a}=this,h=((t,e,s,i,r)=>{const{path:n,pathOffset:o}=t,a=n[(i>0?i:n.length)-1],h=new ot(a[r],a[r+1]),c=h.subtract(o).transform(t.calcOwnMatrix()),l=me(new ot(e,s),void 0,t.calcOwnMatrix());n[i][r]=l.x+o.x,n[i][r+1]=l.y+o.y,t.setDimensions();const u=h.subtract(t.pathOffset).transform(t.calcOwnMatrix()).subtract(c);return t.left-=u.x,t.top-=u.y,t.set("dirty",!0),!0})(n,i,r,o,a);return _e(this.actionName,s(s({},Oe(t,e,i,r)),{},{commandIndex:o,pointIndex:a})),h}class Ea extends ai{constructor(t){super(t)}render(t,e,i,r,n){const o=s(s({},r),{},{cornerColor:this.controlFill,cornerStrokeColor:this.controlStroke,transparentCorners:!this.controlFill});super.render(t,e,i,o,n)}}class Aa extends Ea{constructor(t){super(t)}render(t,e,s,i,r){const{path:n}=r,{commandIndex:o,pointIndex:a,connectToCommandIndex:h,connectToPointIndex:c}=this;t.save(),t.strokeStyle=this.controlStroke,this.connectionDashArray&&t.setLineDash(this.connectionDashArray);const[l]=n[o],u=Da(r,h,c);if("Q"===l){const i=Da(r,o,a+2);t.moveTo(i.x,i.y),t.lineTo(e,s)}else t.moveTo(e,s);t.lineTo(u.x,u.y),t.stroke(),t.restore(),super.render(t,e,s,i,r)}}const ja=(t,e,i,r,n,o)=>new(i?Aa:Ea)(s(s({commandIndex:t,pointIndex:e,actionName:"modifyPath",positionHandler:Ma,actionHandler:Pa,connectToCommandIndex:n,connectToPointIndex:o},r),i?r.controlPointStyle:r.pointStyle));var Fa=Object.freeze({__proto__:null,changeWidth:ri,createObjectDefaultControls:Pi,createPathControls:function(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const s={};let i="M";return t.path.forEach(((t,r)=>{const n=t[0];switch("Z"!==n&&(s["c_".concat(r,"_").concat(n)]=ja(r,t.length-2,!1,e)),n){case"C":s["c_".concat(r,"_C_CP_1")]=ja(r,1,!0,e,r-1,(t=>"C"===t?5:"Q"===t?3:1)(i)),s["c_".concat(r,"_C_CP_2")]=ja(r,3,!0,e,r,5);break;case"Q":s["c_".concat(r,"_Q_CP_1")]=ja(r,1,!0,e,r,3)}i=n})),s},createPolyActionHandler:ka,createPolyControls:function(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};const i={};for(let r=0;r<("number"==typeof t?t:t.points.length);r++)i["p".concat(r)]=new ai(s({actionName:Sa,positionHandler:wa(r),actionHandler:ka(r)},e));return i},createPolyPositionHandler:wa,createResizeControls:Ei,createTextboxDefaultControls:Ai,dragHandler:Me,factoryPolyActionHandler:Oa,getLocalPoint:De,polyActionHandler:Ta,renderCircleControl:ni,renderSquareControl:oi,rotationStyleHandler:hi,rotationWithSnapping:ci,scaleCursorStyleHandler:gi,scaleOrSkewActionName:Oi,scaleSkewCursorStyleHandler:ki,scalingEqually:pi,scalingX:mi,scalingXOrSkewingY:Di,scalingY:vi,scalingYOrSkewingX:Mi,skewCursorStyleHandler:Ci,skewHandlerX:Si,skewHandlerY:wi,wrapWithFireEvent:si,wrapWithFixedAnchor:ii});const La=t=>void 0!==t.webgl,Ra=(t,e)=>{const s=vt({width:t,height:e}),i=pt().getContext("webgl"),r={imageBuffer:new ArrayBuffer(t*e*4)},n={destinationWidth:t,destinationHeight:e,targetCanvas:s};let o;o=v().performance.now(),$o.prototype.copyGLTo2D.call(r,i,n);const a=v().performance.now()-o;o=v().performance.now(),$o.prototype.copyGLTo2DPutImageData.call(r,i,n);return a>v().performance.now()-o},Ia="precision highp float",Ba="\n    ".concat(Ia,";\n    varying vec2 vTexCoord;\n    uniform sampler2D uTexture;\n    void main() {\n      gl_FragColor = texture2D(uTexture, vTexCoord);\n    }"),Xa=["type"],Ya=["type"],Wa=new RegExp(Ia,"g");class Va{get type(){return this.constructor.type}constructor(){let t=i(arguments.length>0&&void 0!==arguments[0]?arguments[0]:{},Xa);Object.assign(this,this.constructor.defaults,t)}getFragmentSource(){return Ba}getVertexSource(){return"\n    attribute vec2 aPosition;\n    varying vec2 vTexCoord;\n    void main() {\n      vTexCoord = aPosition;\n      gl_Position = vec4(aPosition * 2.0 - 1.0, 0.0, 1.0);\n    }"}createProgram(t){let e=arguments.length>1&&void 0!==arguments[1]?arguments[1]:this.getFragmentSource(),s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:this.getVertexSource();const{WebGLProbe:{GLPrecision:i="highp"}}=p();"highp"!==i&&(e=e.replace(Wa,Ia.replace("highp",i)));const r=t.createShader(t.VERTEX_SHADER),n=t.createShader(t.FRAGMENT_SHADER),o=t.createProgram();if(!r||!n||!o)throw new h("Vertex, fragment shader or program creation error");if(t.shaderSource(r,s),t.compileShader(r),!t.getShaderParameter(r,t.COMPILE_STATUS))throw new h("Vertex shader compile error for ".concat(this.type,": ").concat(t.getShaderInfoLog(r)));if(t.shaderSource(n,e),t.compileShader(n),!t.getShaderParameter(n,t.COMPILE_STATUS))throw new h("Fragment shader compile error for ".concat(this.type,": ").concat(t.getShaderInfoLog(n)));if(t.attachShader(o,r),t.attachShader(o,n),t.linkProgram(o),!t.getProgramParameter(o,t.LINK_STATUS))throw new h('Shader link error for "'.concat(this.type,'" ').concat(t.getProgramInfoLog(o)));const a=this.getUniformLocations(t,o)||{};return a.uStepW=t.getUniformLocation(o,"uStepW"),a.uStepH=t.getUniformLocation(o,"uStepH"),{program:o,attributeLocations:this.getAttributeLocations(t,o),uniformLocations:a}}getAttributeLocations(t,e){return{aPosition:t.getAttribLocation(e,"aPosition")}}getUniformLocations(t,e){const s=this.constructor.uniformLocations,i={};for(let r=0;r<s.length;r++)i[s[r]]=t.getUniformLocation(e,s[r]);return i}sendAttributeData(t,e,s){const i=e.aPosition,r=t.createBuffer();t.bindBuffer(t.ARRAY_BUFFER,r),t.enableVertexAttribArray(i),t.vertexAttribPointer(i,2,t.FLOAT,!1,0,0),t.bufferData(t.ARRAY_BUFFER,s,t.STATIC_DRAW)}_setupFrameBuffer(t){const e=t.context;if(t.passes>1){const s=t.destinationWidth,i=t.destinationHeight;t.sourceWidth===s&&t.sourceHeight===i||(e.deleteTexture(t.targetTexture),t.targetTexture=t.filterBackend.createTexture(e,s,i)),e.framebufferTexture2D(e.FRAMEBUFFER,e.COLOR_ATTACHMENT0,e.TEXTURE_2D,t.targetTexture,0)}else e.bindFramebuffer(e.FRAMEBUFFER,null),e.finish()}_swapTextures(t){t.passes--,t.pass++;const e=t.targetTexture;t.targetTexture=t.sourceTexture,t.sourceTexture=e}isNeutralState(t){return!1}applyTo(t){La(t)?(this._setupFrameBuffer(t),this.applyToWebGL(t),this._swapTextures(t)):this.applyTo2d(t)}applyTo2d(t){}getCacheKey(){return this.type}retrieveShader(t){const e=this.getCacheKey();return t.programCache[e]||(t.programCache[e]=this.createProgram(t.context)),t.programCache[e]}applyToWebGL(t){const e=t.context,s=this.retrieveShader(t);0===t.pass&&t.originalTexture?e.bindTexture(e.TEXTURE_2D,t.originalTexture):e.bindTexture(e.TEXTURE_2D,t.sourceTexture),e.useProgram(s.program),this.sendAttributeData(e,s.attributeLocations,t.aPosition),e.uniform1f(s.uniformLocations.uStepW,1/t.sourceWidth),e.uniform1f(s.uniformLocations.uStepH,1/t.sourceHeight),this.sendUniformData(e,s.uniformLocations),e.viewport(0,0,t.destinationWidth,t.destinationHeight),e.drawArrays(e.TRIANGLE_STRIP,0,4)}bindAdditionalTexture(t,e,s){t.activeTexture(s),t.bindTexture(t.TEXTURE_2D,e),t.activeTexture(t.TEXTURE0)}unbindAdditionalTexture(t,e){t.activeTexture(e),t.bindTexture(t.TEXTURE_2D,null),t.activeTexture(t.TEXTURE0)}sendUniformData(t,e){}createHelpLayer(t){if(!t.helpLayer){const{sourceWidth:e,sourceHeight:s}=t,i=vt({width:e,height:s});t.helpLayer=i}}toObject(){const t=Object.keys(this.constructor.defaults||{});return s({type:this.type},t.reduce(((t,e)=>(t[e]=this[e],t)),{}))}toJSON(){return this.toObject()}static async fromObject(t,e){return new this(i(t,Ya))}}t(Va,"type","BaseFilter"),t(Va,"uniformLocations",[]);const za={multiply:"gl_FragColor.rgb *= uColor.rgb;\n",screen:"gl_FragColor.rgb = 1.0 - (1.0 - gl_FragColor.rgb) * (1.0 - uColor.rgb);\n",add:"gl_FragColor.rgb += uColor.rgb;\n",difference:"gl_FragColor.rgb = abs(gl_FragColor.rgb - uColor.rgb);\n",subtract:"gl_FragColor.rgb -= uColor.rgb;\n",lighten:"gl_FragColor.rgb = max(gl_FragColor.rgb, uColor.rgb);\n",darken:"gl_FragColor.rgb = min(gl_FragColor.rgb, uColor.rgb);\n",exclusion:"gl_FragColor.rgb += uColor.rgb - 2.0 * (uColor.rgb * gl_FragColor.rgb);\n",overlay:"\n    if (uColor.r < 0.5) {\n      gl_FragColor.r *= 2.0 * uColor.r;\n    } else {\n      gl_FragColor.r = 1.0 - 2.0 * (1.0 - gl_FragColor.r) * (1.0 - uColor.r);\n    }\n    if (uColor.g < 0.5) {\n      gl_FragColor.g *= 2.0 * uColor.g;\n    } else {\n      gl_FragColor.g = 1.0 - 2.0 * (1.0 - gl_FragColor.g) * (1.0 - uColor.g);\n    }\n    if (uColor.b < 0.5) {\n      gl_FragColor.b *= 2.0 * uColor.b;\n    } else {\n      gl_FragColor.b = 1.0 - 2.0 * (1.0 - gl_FragColor.b) * (1.0 - uColor.b);\n    }\n    ",tint:"\n    gl_FragColor.rgb *= (1.0 - uColor.a);\n    gl_FragColor.rgb += uColor.rgb;\n    "};class Ga extends Va{getCacheKey(){return"".concat(this.type,"_").concat(this.mode)}getFragmentSource(){return"\n      precision highp float;\n      uniform sampler2D uTexture;\n      uniform vec4 uColor;\n      varying vec2 vTexCoord;\n      void main() {\n        vec4 color = texture2D(uTexture, vTexCoord);\n        gl_FragColor = color;\n        if (color.a > 0.0) {\n          ".concat(za[this.mode],"\n        }\n      }\n      ")}applyTo2d(t){let{imageData:{data:e}}=t;const s=new Ie(this.color).getSource(),i=this.alpha,r=s[0]*i,n=s[1]*i,o=s[2]*i,a=1-i;for(let t=0;t<e.length;t+=4){const s=e[t],i=e[t+1],h=e[t+2];let c,l,u;switch(this.mode){case"multiply":c=s*r/255,l=i*n/255,u=h*o/255;break;case"screen":c=255-(255-s)*(255-r)/255,l=255-(255-i)*(255-n)/255,u=255-(255-h)*(255-o)/255;break;case"add":c=s+r,l=i+n,u=h+o;break;case"difference":c=Math.abs(s-r),l=Math.abs(i-n),u=Math.abs(h-o);break;case"subtract":c=s-r,l=i-n,u=h-o;break;case"darken":c=Math.min(s,r),l=Math.min(i,n),u=Math.min(h,o);break;case"lighten":c=Math.max(s,r),l=Math.max(i,n),u=Math.max(h,o);break;case"overlay":c=r<128?2*s*r/255:255-2*(255-s)*(255-r)/255,l=n<128?2*i*n/255:255-2*(255-i)*(255-n)/255,u=o<128?2*h*o/255:255-2*(255-h)*(255-o)/255;break;case"exclusion":c=r+s-2*r*s/255,l=n+i-2*n*i/255,u=o+h-2*o*h/255;break;case"tint":c=r+s*a,l=n+i*a,u=o+h*a}e[t]=c,e[t+1]=l,e[t+2]=u}}sendUniformData(t,e){const s=new Ie(this.color).getSource();s[0]=this.alpha*s[0]/255,s[1]=this.alpha*s[1]/255,s[2]=this.alpha*s[2]/255,s[3]=this.alpha,t.uniform4fv(e.uColor,s)}}t(Ga,"defaults",{color:"#F95C63",mode:"multiply",alpha:1}),t(Ga,"type","BlendColor"),t(Ga,"uniformLocations",["uColor"]),tt.setClass(Ga);const Ha={multiply:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform sampler2D uImage;\n    uniform vec4 uColor;\n    varying vec2 vTexCoord;\n    varying vec2 vTexCoord2;\n    void main() {\n      vec4 color = texture2D(uTexture, vTexCoord);\n      vec4 color2 = texture2D(uImage, vTexCoord2);\n      color.rgba *= color2.rgba;\n      gl_FragColor = color;\n    }\n    ",mask:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform sampler2D uImage;\n    uniform vec4 uColor;\n    varying vec2 vTexCoord;\n    varying vec2 vTexCoord2;\n    void main() {\n      vec4 color = texture2D(uTexture, vTexCoord);\n      vec4 color2 = texture2D(uImage, vTexCoord2);\n      color.a = color2.a;\n      gl_FragColor = color;\n    }\n    "},Na=["type","image"];class Ua extends Va{getCacheKey(){return"".concat(this.type,"_").concat(this.mode)}getFragmentSource(){return Ha[this.mode]}getVertexSource(){return"\n    attribute vec2 aPosition;\n    varying vec2 vTexCoord;\n    varying vec2 vTexCoord2;\n    uniform mat3 uTransformMatrix;\n    void main() {\n      vTexCoord = aPosition;\n      vTexCoord2 = (uTransformMatrix * vec3(aPosition, 1.0)).xy;\n      gl_Position = vec4(aPosition * 2.0 - 1.0, 0.0, 1.0);\n    }\n    "}applyToWebGL(t){const e=t.context,s=this.createTexture(t.filterBackend,this.image);this.bindAdditionalTexture(e,s,e.TEXTURE1),super.applyToWebGL(t),this.unbindAdditionalTexture(e,e.TEXTURE1)}createTexture(t,e){return t.getCachedTexture(e.cacheKey,e.getElement())}calculateMatrix(){const t=this.image,{width:e,height:s}=t.getElement();return[1/t.scaleX,0,0,0,1/t.scaleY,0,-t.left/e,-t.top/s,1]}applyTo2d(t){let{imageData:{data:e,width:s,height:i},filterBackend:{resources:r}}=t;const n=this.image;r.blendImage||(r.blendImage=pt());const o=r.blendImage,a=o.getContext("2d");o.width!==s||o.height!==i?(o.width=s,o.height=i):a.clearRect(0,0,s,i),a.setTransform(n.scaleX,0,0,n.scaleY,n.left,n.top),a.drawImage(n.getElement(),0,0,s,i);const h=a.getImageData(0,0,s,i).data;for(let t=0;t<e.length;t+=4){const s=e[t],i=e[t+1],r=e[t+2],n=e[t+3],o=h[t],a=h[t+1],c=h[t+2],l=h[t+3];switch(this.mode){case"multiply":e[t]=s*o/255,e[t+1]=i*a/255,e[t+2]=r*c/255,e[t+3]=n*l/255;break;case"mask":e[t+3]=l}}}sendUniformData(t,e){const s=this.calculateMatrix();t.uniform1i(e.uImage,1),t.uniformMatrix3fv(e.uTransformMatrix,!1,s)}toObject(){return s(s({},super.toObject()),{},{image:this.image&&this.image.toObject()})}static async fromObject(t,e){let{type:r,image:n}=t,o=i(t,Na);return oa.fromObject(n,e).then((t=>new this(s(s({},o),{},{image:t}))))}}t(Ua,"type","BlendImage"),t(Ua,"defaults",{mode:"multiply",alpha:1}),t(Ua,"uniformLocations",["uTransformMatrix","uImage"]),tt.setClass(Ua);class qa extends Va{getFragmentSource(){return"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform vec2 uDelta;\n    varying vec2 vTexCoord;\n    const float nSamples = 15.0;\n    vec3 v3offset = vec3(12.9898, 78.233, 151.7182);\n    float random(vec3 scale) {\n      /* use the fragment position for a different seed per-pixel */\n      return fract(sin(dot(gl_FragCoord.xyz, scale)) * 43758.5453);\n    }\n    void main() {\n      vec4 color = vec4(0.0);\n      float totalC = 0.0;\n      float totalA = 0.0;\n      float offset = random(v3offset);\n      for (float t = -nSamples; t <= nSamples; t++) {\n        float percent = (t + offset - 0.5) / nSamples;\n        vec4 sample = texture2D(uTexture, vTexCoord + uDelta * percent);\n        float weight = 1.0 - abs(percent);\n        float alpha = weight * sample.a;\n        color.rgb += sample.rgb * alpha;\n        color.a += alpha;\n        totalA += weight;\n        totalC += alpha;\n      }\n      gl_FragColor.rgb = color.rgb / totalC;\n      gl_FragColor.a = color.a / totalA;\n    }\n  "}applyTo(t){La(t)?(this.aspectRatio=t.sourceWidth/t.sourceHeight,t.passes++,this._setupFrameBuffer(t),this.horizontal=!0,this.applyToWebGL(t),this._swapTextures(t),this._setupFrameBuffer(t),this.horizontal=!1,this.applyToWebGL(t),this._swapTextures(t)):this.applyTo2d(t)}applyTo2d(t){let{imageData:{data:e,width:s,height:i}}=t;this.aspectRatio=s/i,this.horizontal=!0;let r=this.getBlurValue()*s;const n=new Uint8ClampedArray(e),o=15,a=4*s;for(let t=0;t<e.length;t+=4){let s=0,i=0,h=0,c=0,l=0;const u=t-t%a,d=u+a;for(let n=-14;n<o;n++){const a=n/o,g=4*Math.floor(r*a),f=1-Math.abs(a);let p=t+g;p<u?p=u:p>d&&(p=d);const m=e[p+3]*f;s+=e[p]*m,i+=e[p+1]*m,h+=e[p+2]*m,c+=m,l+=f}n[t]=s/c,n[t+1]=i/c,n[t+2]=h/c,n[t+3]=c/l}this.horizontal=!1,r=this.getBlurValue()*i;for(let t=0;t<n.length;t+=4){let s=0,i=0,h=0,c=0,l=0;const u=t%a,d=n.length-a+u;for(let e=-14;e<o;e++){const g=e/o,f=Math.floor(r*g)*a,p=1-Math.abs(g);let m=t+f;m<u?m=u:m>d&&(m=d);const v=n[m+3]*p;s+=n[m]*v,i+=n[m+1]*v,h+=n[m+2]*v,c+=v,l+=p}e[t]=s/c,e[t+1]=i/c,e[t+2]=h/c,e[t+3]=c/l}}sendUniformData(t,e){const s=this.chooseRightDelta();t.uniform2fv(e.uDelta,s)}isNeutralState(){return 0===this.blur}getBlurValue(){let t=1;const{horizontal:e,aspectRatio:s}=this;return e?s>1&&(t=1/s):s<1&&(t=s),t*this.blur*.12}chooseRightDelta(){const t=this.getBlurValue();return this.horizontal?[t,0]:[0,t]}}t(qa,"type","Blur"),t(qa,"defaults",{blur:0}),t(qa,"uniformLocations",["uDelta"]),tt.setClass(qa);class Ka extends Va{getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform float uBrightness;\n  varying vec2 vTexCoord;\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    color.rgb += uBrightness;\n    gl_FragColor = color;\n  }\n"}applyTo2d(t){let{imageData:{data:e}}=t;const s=Math.round(255*this.brightness);for(let t=0;t<e.length;t+=4)e[t]+=s,e[t+1]+=s,e[t+2]+=s}isNeutralState(){return 0===this.brightness}sendUniformData(t,e){t.uniform1f(e.uBrightness,this.brightness)}}t(Ka,"type","Brightness"),t(Ka,"defaults",{brightness:0}),t(Ka,"uniformLocations",["uBrightness"]),tt.setClass(Ka);const Ja={matrix:[1,0,0,0,0,0,1,0,0,0,0,0,1,0,0,0,0,0,1,0],colorsOnly:!0};class Qa extends Va{getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  varying vec2 vTexCoord;\n  uniform mat4 uColorMatrix;\n  uniform vec4 uConstants;\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    color *= uColorMatrix;\n    color += uConstants;\n    gl_FragColor = color;\n  }"}applyTo2d(t){const e=t.imageData.data,s=this.matrix,i=this.colorsOnly;for(let t=0;t<e.length;t+=4){const r=e[t],n=e[t+1],o=e[t+2];if(e[t]=r*s[0]+n*s[1]+o*s[2]+255*s[4],e[t+1]=r*s[5]+n*s[6]+o*s[7]+255*s[9],e[t+2]=r*s[10]+n*s[11]+o*s[12]+255*s[14],!i){const i=e[t+3];e[t]+=i*s[3],e[t+1]+=i*s[8],e[t+2]+=i*s[13],e[t+3]=r*s[15]+n*s[16]+o*s[17]+i*s[18]+255*s[19]}}}sendUniformData(t,e){const s=this.matrix,i=[s[0],s[1],s[2],s[3],s[5],s[6],s[7],s[8],s[10],s[11],s[12],s[13],s[15],s[16],s[17],s[18]],r=[s[4],s[9],s[14],s[19]];t.uniformMatrix4fv(e.uColorMatrix,!1,i),t.uniform4fv(e.uConstants,r)}toObject(){return s(s({},super.toObject()),{},{matrix:[...this.matrix]})}}function Za(e,s){var i;const r=(t(i=class extends Qa{toObject(){return{type:this.type,colorsOnly:this.colorsOnly}}},"type",e),t(i,"defaults",{colorsOnly:!1,matrix:s}),i);return tt.setClass(r,e),r}t(Qa,"type","ColorMatrix"),t(Qa,"defaults",Ja),t(Qa,"uniformLocations",["uColorMatrix","uConstants"]),tt.setClass(Qa);const $a=Za("Brownie",[.5997,.34553,-.27082,0,.186,-.0377,.86095,.15059,0,-.1449,.24113,-.07441,.44972,0,-.02965,0,0,0,1,0]),th=Za("Vintage",[.62793,.32021,-.03965,0,.03784,.02578,.64411,.03259,0,.02926,.0466,-.08512,.52416,0,.02023,0,0,0,1,0]),eh=Za("Kodachrome",[1.12855,-.39673,-.03992,0,.24991,-.16404,1.08352,-.05498,0,.09698,-.16786,-.56034,1.60148,0,.13972,0,0,0,1,0]),sh=Za("Technicolor",[1.91252,-.85453,-.09155,0,.04624,-.30878,1.76589,-.10601,0,-.27589,-.2311,-.75018,1.84759,0,.12137,0,0,0,1,0]),ih=Za("Polaroid",[1.438,-.062,-.062,0,0,-.122,1.378,-.122,0,0,-.016,-.016,1.483,0,0,0,0,0,1,0]),rh=Za("Sepia",[.393,.769,.189,0,0,.349,.686,.168,0,0,.272,.534,.131,0,0,0,0,0,1,0]),nh=Za("BlackWhite",[1.5,1.5,1.5,0,-1,1.5,1.5,1.5,0,-1,1.5,1.5,1.5,0,-1,0,0,0,1,0]);class oh extends Va{constructor(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};super(t),this.subFilters=t.subFilters||[]}applyTo(t){La(t)&&(t.passes+=this.subFilters.length-1),this.subFilters.forEach((e=>{e.applyTo(t)}))}toObject(){return{type:this.type,subFilters:this.subFilters.map((t=>t.toObject()))}}isNeutralState(){return!this.subFilters.some((t=>!t.isNeutralState()))}static fromObject(t,e){return Promise.all((t.subFilters||[]).map((t=>tt.getClass(t.type).fromObject(t,e)))).then((t=>new this({subFilters:t})))}}t(oh,"type","Composed"),tt.setClass(oh);class ah extends Va{getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform float uContrast;\n  varying vec2 vTexCoord;\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    float contrastF = 1.015 * (uContrast + 1.0) / (1.0 * (1.015 - uContrast));\n    color.rgb = contrastF * (color.rgb - 0.5) + 0.5;\n    gl_FragColor = color;\n  }"}isNeutralState(){return 0===this.contrast}applyTo2d(t){let{imageData:{data:e}}=t;const s=Math.floor(255*this.contrast),i=259*(s+255)/(255*(259-s));for(let t=0;t<e.length;t+=4)e[t]=i*(e[t]-128)+128,e[t+1]=i*(e[t+1]-128)+128,e[t+2]=i*(e[t+2]-128)+128}sendUniformData(t,e){t.uniform1f(e.uContrast,this.contrast)}}t(ah,"type","Contrast"),t(ah,"defaults",{contrast:0}),t(ah,"uniformLocations",["uContrast"]),tt.setClass(ah);const hh={Convolute_3_1:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[9];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 0);\n      for (float h = 0.0; h < 3.0; h+=1.0) {\n        for (float w = 0.0; w < 3.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 1), uStepH * (h - 1));\n          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 3.0 + w)];\n        }\n      }\n      gl_FragColor = color;\n    }\n    ",Convolute_3_0:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[9];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 1);\n      for (float h = 0.0; h < 3.0; h+=1.0) {\n        for (float w = 0.0; w < 3.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 1.0), uStepH * (h - 1.0));\n          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 3.0 + w)];\n        }\n      }\n      float alpha = texture2D(uTexture, vTexCoord).a;\n      gl_FragColor = color;\n      gl_FragColor.a = alpha;\n    }\n    ",Convolute_5_1:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[25];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 0);\n      for (float h = 0.0; h < 5.0; h+=1.0) {\n        for (float w = 0.0; w < 5.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 2.0), uStepH * (h - 2.0));\n          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 5.0 + w)];\n        }\n      }\n      gl_FragColor = color;\n    }\n    ",Convolute_5_0:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[25];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 1);\n      for (float h = 0.0; h < 5.0; h+=1.0) {\n        for (float w = 0.0; w < 5.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 2.0), uStepH * (h - 2.0));\n          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 5.0 + w)];\n        }\n      }\n      float alpha = texture2D(uTexture, vTexCoord).a;\n      gl_FragColor = color;\n      gl_FragColor.a = alpha;\n    }\n    ",Convolute_7_1:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[49];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 0);\n      for (float h = 0.0; h < 7.0; h+=1.0) {\n        for (float w = 0.0; w < 7.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 3.0), uStepH * (h - 3.0));\n          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 7.0 + w)];\n        }\n      }\n      gl_FragColor = color;\n    }\n    ",Convolute_7_0:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[49];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 1);\n      for (float h = 0.0; h < 7.0; h+=1.0) {\n        for (float w = 0.0; w < 7.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 3.0), uStepH * (h - 3.0));\n          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 7.0 + w)];\n        }\n      }\n      float alpha = texture2D(uTexture, vTexCoord).a;\n      gl_FragColor = color;\n      gl_FragColor.a = alpha;\n    }\n    ",Convolute_9_1:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[81];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 0);\n      for (float h = 0.0; h < 9.0; h+=1.0) {\n        for (float w = 0.0; w < 9.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 4.0), uStepH * (h - 4.0));\n          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 9.0 + w)];\n        }\n      }\n      gl_FragColor = color;\n    }\n    ",Convolute_9_0:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform float uMatrix[81];\n    uniform float uStepW;\n    uniform float uStepH;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = vec4(0, 0, 0, 1);\n      for (float h = 0.0; h < 9.0; h+=1.0) {\n        for (float w = 0.0; w < 9.0; w+=1.0) {\n          vec2 matrixPos = vec2(uStepW * (w - 4.0), uStepH * (h - 4.0));\n          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 9.0 + w)];\n        }\n      }\n      float alpha = texture2D(uTexture, vTexCoord).a;\n      gl_FragColor = color;\n      gl_FragColor.a = alpha;\n    }\n    "};class ch extends Va{getCacheKey(){return"".concat(this.type,"_").concat(Math.sqrt(this.matrix.length),"_").concat(this.opaque?1:0)}getFragmentSource(){return hh[this.getCacheKey()]}applyTo2d(t){const e=t.imageData,s=e.data,i=this.matrix,r=Math.round(Math.sqrt(i.length)),n=Math.floor(r/2),o=e.width,a=e.height,h=t.ctx.createImageData(o,a),c=h.data,l=this.opaque?1:0;let u,d,g,f,p,m,v,y,_,x,C,b,S;for(C=0;C<a;C++)for(x=0;x<o;x++){for(p=4*(C*o+x),u=0,d=0,g=0,f=0,S=0;S<r;S++)for(b=0;b<r;b++)v=C+S-n,m=x+b-n,v<0||v>=a||m<0||m>=o||(y=4*(v*o+m),_=i[S*r+b],u+=s[y]*_,d+=s[y+1]*_,g+=s[y+2]*_,l||(f+=s[y+3]*_));c[p]=u,c[p+1]=d,c[p+2]=g,c[p+3]=l?s[p+3]:f}t.imageData=h}sendUniformData(t,e){t.uniform1fv(e.uMatrix,this.matrix)}toObject(){return s(s({},super.toObject()),{},{opaque:this.opaque,matrix:[...this.matrix]})}}t(ch,"type","Convolute"),t(ch,"defaults",{opaque:!1,matrix:[0,0,0,0,1,0,0,0,0]}),t(ch,"uniformLocations",["uMatrix","uOpaque","uHalfSize","uSize"]),tt.setClass(ch);const lh="Gamma";class uh extends Va{getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform vec3 uGamma;\n  varying vec2 vTexCoord;\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    vec3 correction = (1.0 / uGamma);\n    color.r = pow(color.r, correction.r);\n    color.g = pow(color.g, correction.g);\n    color.b = pow(color.b, correction.b);\n    gl_FragColor = color;\n    gl_FragColor.rgb *= color.a;\n  }\n"}constructor(){let t=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{};super(t),this.gamma=t.gamma||this.constructor.defaults.gamma.concat()}applyTo2d(t){let{imageData:{data:e}}=t;const s=this.gamma,i=1/s[0],r=1/s[1],n=1/s[2];this.rgbValues||(this.rgbValues={r:new Uint8Array(256),g:new Uint8Array(256),b:new Uint8Array(256)});const o=this.rgbValues;for(let t=0;t<256;t++)o.r[t]=255*Math.pow(t/255,i),o.g[t]=255*Math.pow(t/255,r),o.b[t]=255*Math.pow(t/255,n);for(let t=0;t<e.length;t+=4)e[t]=o.r[e[t]],e[t+1]=o.g[e[t+1]],e[t+2]=o.b[e[t+2]]}sendUniformData(t,e){t.uniform3fv(e.uGamma,this.gamma)}isNeutralState(){const{gamma:t}=this;return 1===t[0]&&1===t[1]&&1===t[2]}toObject(){return{type:lh,gamma:this.gamma.concat()}}}t(uh,"type",lh),t(uh,"defaults",{gamma:[1,1,1]}),t(uh,"uniformLocations",["uGamma"]),tt.setClass(uh);const dh={average:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 color = texture2D(uTexture, vTexCoord);\n      float average = (color.r + color.b + color.g) / 3.0;\n      gl_FragColor = vec4(average, average, average, color.a);\n    }\n    ",lightness:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform int uMode;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 col = texture2D(uTexture, vTexCoord);\n      float average = (max(max(col.r, col.g),col.b) + min(min(col.r, col.g),col.b)) / 2.0;\n      gl_FragColor = vec4(average, average, average, col.a);\n    }\n    ",luminosity:"\n    precision highp float;\n    uniform sampler2D uTexture;\n    uniform int uMode;\n    varying vec2 vTexCoord;\n    void main() {\n      vec4 col = texture2D(uTexture, vTexCoord);\n      float average = 0.21 * col.r + 0.72 * col.g + 0.07 * col.b;\n      gl_FragColor = vec4(average, average, average, col.a);\n    }\n    "};class gh extends Va{applyTo2d(t){let{imageData:{data:e}}=t;for(let t,s=0;s<e.length;s+=4){const i=e[s],r=e[s+1],n=e[s+2];switch(this.mode){case"average":t=(i+r+n)/3;break;case"lightness":t=(Math.min(i,r,n)+Math.max(i,r,n))/2;break;case"luminosity":t=.21*i+.72*r+.07*n}e[s+2]=e[s+1]=e[s]=t}}getCacheKey(){return"".concat(this.type,"_").concat(this.mode)}getFragmentSource(){return dh[this.mode]}sendUniformData(t,e){t.uniform1i(e.uMode,1)}isNeutralState(){return!1}}t(gh,"type","Grayscale"),t(gh,"defaults",{mode:"average"}),t(gh,"uniformLocations",["uMode"]),tt.setClass(gh);const fh=s(s({},Ja),{},{rotation:0});class ph extends Qa{calculateMatrix(){const t=this.rotation*Math.PI,e=rt(t),s=nt(t),i=1/3,r=Math.sqrt(i)*s,n=1-e;this.matrix=[e+n/3,i*n-r,i*n+r,0,0,i*n+r,e+i*n,i*n-r,0,0,i*n-r,i*n+r,e+i*n,0,0,0,0,0,1,0]}isNeutralState(){return 0===this.rotation}applyTo(t){this.calculateMatrix(),super.applyTo(t)}toObject(){return{type:this.type,rotation:this.rotation}}}t(ph,"type","HueRotation"),t(ph,"defaults",fh),tt.setClass(ph);class mh extends Va{applyTo2d(t){let{imageData:{data:e}}=t;for(let t=0;t<e.length;t+=4)e[t]=255-e[t],e[t+1]=255-e[t+1],e[t+2]=255-e[t+2],this.alpha&&(e[t+3]=255-e[t+3])}getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform int uInvert;\n  uniform int uAlpha;\n  varying vec2 vTexCoord;\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    if (uInvert == 1) {\n      if (uAlpha == 1) {\n        gl_FragColor = vec4(1.0 - color.r,1.0 -color.g,1.0 -color.b,1.0 -color.a);\n      } else {\n        gl_FragColor = vec4(1.0 - color.r,1.0 -color.g,1.0 -color.b,color.a);\n      }\n    } else {\n      gl_FragColor = color;\n    }\n  }\n"}isNeutralState(){return!this.invert}sendUniformData(t,e){t.uniform1i(e.uInvert,Number(this.invert)),t.uniform1i(e.uAlpha,Number(this.alpha))}}t(mh,"type","Invert"),t(mh,"defaults",{alpha:!1,invert:!0}),t(mh,"uniformLocations",["uInvert","uAlpha"]),tt.setClass(mh);class vh extends Va{getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform float uStepH;\n  uniform float uNoise;\n  uniform float uSeed;\n  varying vec2 vTexCoord;\n  float rand(vec2 co, float seed, float vScale) {\n    return fract(sin(dot(co.xy * vScale ,vec2(12.9898 , 78.233))) * 43758.5453 * (seed + 0.01) / 2.0);\n  }\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    color.rgb += (0.5 - rand(vTexCoord, uSeed, 0.1 / uStepH)) * uNoise;\n    gl_FragColor = color;\n  }\n"}applyTo2d(t){let{imageData:{data:e}}=t;const s=this.noise;for(let t=0;t<e.length;t+=4){const i=(.5-Math.random())*s;e[t]+=i,e[t+1]+=i,e[t+2]+=i}}sendUniformData(t,e){t.uniform1f(e.uNoise,this.noise/255),t.uniform1f(e.uSeed,Math.random())}isNeutralState(){return 0===this.noise}}t(vh,"type","Noise"),t(vh,"defaults",{noise:0}),t(vh,"uniformLocations",["uNoise","uSeed"]),tt.setClass(vh);class yh extends Va{applyTo2d(t){let{imageData:{data:e,width:s,height:i}}=t;for(let t=0;t<i;t+=this.blocksize)for(let r=0;r<s;r+=this.blocksize){const n=4*t*s+4*r,o=e[n],a=e[n+1],h=e[n+2],c=e[n+3];for(let n=t;n<Math.min(t+this.blocksize,i);n++)for(let t=r;t<Math.min(r+this.blocksize,s);t++){const i=4*n*s+4*t;e[i]=o,e[i+1]=a,e[i+2]=h,e[i+3]=c}}}isNeutralState(){return 1===this.blocksize}getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform float uBlocksize;\n  uniform float uStepW;\n  uniform float uStepH;\n  varying vec2 vTexCoord;\n  void main() {\n    float blockW = uBlocksize * uStepW;\n    float blockH = uBlocksize * uStepH;\n    int posX = int(vTexCoord.x / blockW);\n    int posY = int(vTexCoord.y / blockH);\n    float fposX = float(posX);\n    float fposY = float(posY);\n    vec2 squareCoords = vec2(fposX * blockW, fposY * blockH);\n    vec4 color = texture2D(uTexture, squareCoords);\n    gl_FragColor = color;\n  }\n"}sendUniformData(t,e){t.uniform1f(e.uBlocksize,this.blocksize)}}t(yh,"type","Pixelate"),t(yh,"defaults",{blocksize:4}),t(yh,"uniformLocations",["uBlocksize"]),tt.setClass(yh);class _h extends Va{getFragmentSource(){return"\nprecision highp float;\nuniform sampler2D uTexture;\nuniform vec4 uLow;\nuniform vec4 uHigh;\nvarying vec2 vTexCoord;\nvoid main() {\n  gl_FragColor = texture2D(uTexture, vTexCoord);\n  if(all(greaterThan(gl_FragColor.rgb,uLow.rgb)) && all(greaterThan(uHigh.rgb,gl_FragColor.rgb))) {\n    gl_FragColor.a = 0.0;\n  }\n}\n"}applyTo2d(t){let{imageData:{data:e}}=t;const s=255*this.distance,i=new Ie(this.color).getSource(),r=[i[0]-s,i[1]-s,i[2]-s],n=[i[0]+s,i[1]+s,i[2]+s];for(let t=0;t<e.length;t+=4){const s=e[t],i=e[t+1],o=e[t+2];s>r[0]&&i>r[1]&&o>r[2]&&s<n[0]&&i<n[1]&&o<n[2]&&(e[t+3]=0)}}sendUniformData(t,e){const s=new Ie(this.color).getSource(),i=this.distance,r=[0+s[0]/255-i,0+s[1]/255-i,0+s[2]/255-i,1],n=[s[0]/255+i,s[1]/255+i,s[2]/255+i,1];t.uniform4fv(e.uLow,r),t.uniform4fv(e.uHigh,n)}}t(_h,"type","RemoveColor"),t(_h,"defaults",{color:"#FFFFFF",distance:.02,useAlpha:!1}),t(_h,"uniformLocations",["uLow","uHigh"]),tt.setClass(_h);class xh extends Va{sendUniformData(t,e){t.uniform2fv(e.uDelta,this.horizontal?[1/this.width,0]:[0,1/this.height]),t.uniform1fv(e.uTaps,this.taps)}getFilterWindow(){const t=this.tempScale;return Math.ceil(this.lanczosLobes/t)}getCacheKey(){const t=this.getFilterWindow();return"".concat(this.type,"_").concat(t)}getFragmentSource(){const t=this.getFilterWindow();return this.generateShader(t)}getTaps(){const t=this.lanczosCreate(this.lanczosLobes),e=this.tempScale,s=this.getFilterWindow(),i=new Array(s);for(let r=1;r<=s;r++)i[r-1]=t(r*e);return i}generateShader(t){const e=new Array(t);for(let s=1;s<=t;s++)e[s-1]="".concat(s,".0 * uDelta");return"\n      precision highp float;\n      uniform sampler2D uTexture;\n      uniform vec2 uDelta;\n      varying vec2 vTexCoord;\n      uniform float uTaps[".concat(t,"];\n      void main() {\n        vec4 color = texture2D(uTexture, vTexCoord);\n        float sum = 1.0;\n        ").concat(e.map(((t,e)=>"\n              color += texture2D(uTexture, vTexCoord + ".concat(t,") * uTaps[").concat(e,"] + texture2D(uTexture, vTexCoord - ").concat(t,") * uTaps[").concat(e,"];\n              sum += 2.0 * uTaps[").concat(e,"];\n            "))).join("\n"),"\n        gl_FragColor = color / sum;\n      }\n    ")}applyToForWebgl(t){t.passes++,this.width=t.sourceWidth,this.horizontal=!0,this.dW=Math.round(this.width*this.scaleX),this.dH=t.sourceHeight,this.tempScale=this.dW/this.width,this.taps=this.getTaps(),t.destinationWidth=this.dW,super.applyTo(t),t.sourceWidth=t.destinationWidth,this.height=t.sourceHeight,this.horizontal=!1,this.dH=Math.round(this.height*this.scaleY),this.tempScale=this.dH/this.height,this.taps=this.getTaps(),t.destinationHeight=this.dH,super.applyTo(t),t.sourceHeight=t.destinationHeight}applyTo(t){La(t)?this.applyToForWebgl(t):this.applyTo2d(t)}isNeutralState(){return 1===this.scaleX&&1===this.scaleY}lanczosCreate(t){return e=>{if(e>=t||e<=-t)return 0;if(e<1.1920929e-7&&e>-1.1920929e-7)return 1;const s=(e*=Math.PI)/t;return Math.sin(e)/e*Math.sin(s)/s}}applyTo2d(t){const e=t.imageData,s=this.scaleX,i=this.scaleY;this.rcpScaleX=1/s,this.rcpScaleY=1/i;const r=e.width,n=e.height,o=Math.round(r*s),a=Math.round(n*i);let h;h="sliceHack"===this.resizeType?this.sliceByTwo(t,r,n,o,a):"hermite"===this.resizeType?this.hermiteFastResize(t,r,n,o,a):"bilinear"===this.resizeType?this.bilinearFiltering(t,r,n,o,a):"lanczos"===this.resizeType?this.lanczosResize(t,r,n,o,a):new ImageData(o,a),t.imageData=h}sliceByTwo(t,e,s,i,r){const n=t.imageData,o=.5;let a=!1,h=!1,c=e*o,l=s*o;const u=t.filterBackend.resources;let d=0,g=0;const f=e;let p=0;u.sliceByTwo||(u.sliceByTwo=pt());const m=u.sliceByTwo;(m.width<1.5*e||m.height<s)&&(m.width=1.5*e,m.height=s);const v=m.getContext("2d");for(v.clearRect(0,0,1.5*e,s),v.putImageData(n,0,0),i=Math.floor(i),r=Math.floor(r);!a||!h;)e=c,s=l,i<Math.floor(c*o)?c=Math.floor(c*o):(c=i,a=!0),r<Math.floor(l*o)?l=Math.floor(l*o):(l=r,h=!0),v.drawImage(m,d,g,e,s,f,p,c,l),d=f,g=p,p+=l;return v.getImageData(d,g,i,r)}lanczosResize(t,e,s,i,r){const n=t.imageData.data,o=t.ctx.createImageData(i,r),a=o.data,h=this.lanczosCreate(this.lanczosLobes),c=this.rcpScaleX,l=this.rcpScaleY,u=2/this.rcpScaleX,d=2/this.rcpScaleY,g=Math.ceil(c*this.lanczosLobes/2),f=Math.ceil(l*this.lanczosLobes/2),p={},m={x:0,y:0},v={x:0,y:0};return function t(y){let _,x,C,b,S,w,T,O,k,D,M;for(m.x=(y+.5)*c,v.x=Math.floor(m.x),_=0;_<r;_++){for(m.y=(_+.5)*l,v.y=Math.floor(m.y),S=0,w=0,T=0,O=0,k=0,x=v.x-g;x<=v.x+g;x++)if(!(x<0||x>=e)){D=Math.floor(1e3*Math.abs(x-m.x)),p[D]||(p[D]={});for(let t=v.y-f;t<=v.y+f;t++)t<0||t>=s||(M=Math.floor(1e3*Math.abs(t-m.y)),p[D][M]||(p[D][M]=h(Math.sqrt(Math.pow(D*u,2)+Math.pow(M*d,2))/1e3)),C=p[D][M],C>0&&(b=4*(t*e+x),S+=C,w+=C*n[b],T+=C*n[b+1],O+=C*n[b+2],k+=C*n[b+3]))}b=4*(_*i+y),a[b]=w/S,a[b+1]=T/S,a[b+2]=O/S,a[b+3]=k/S}return++y<i?t(y):o}(0)}bilinearFiltering(t,e,s,i,r){let n,o,a,h,c,l,u,d,g,f,p,m,v,y=0;const _=this.rcpScaleX,x=this.rcpScaleY,C=4*(e-1),b=t.imageData.data,S=t.ctx.createImageData(i,r),w=S.data;for(u=0;u<r;u++)for(d=0;d<i;d++)for(c=Math.floor(_*d),l=Math.floor(x*u),g=_*d-c,f=x*u-l,v=4*(l*e+c),p=0;p<4;p++)n=b[v+p],o=b[v+4+p],a=b[v+C+p],h=b[v+C+4+p],m=n*(1-g)*(1-f)+o*g*(1-f)+a*f*(1-g)+h*g*f,w[y++]=m;return S}hermiteFastResize(t,e,s,i,r){const n=this.rcpScaleX,o=this.rcpScaleY,a=Math.ceil(n/2),h=Math.ceil(o/2),c=t.imageData.data,l=t.ctx.createImageData(i,r),u=l.data;for(let t=0;t<r;t++)for(let s=0;s<i;s++){const r=4*(s+t*i);let l=0,d=0,g=0,f=0,p=0,m=0,v=0;const y=(t+.5)*o;for(let i=Math.floor(t*o);i<(t+1)*o;i++){const t=Math.abs(y-(i+.5))/h,r=(s+.5)*n,o=t*t;for(let t=Math.floor(s*n);t<(s+1)*n;t++){let s=Math.abs(r-(t+.5))/a;const n=Math.sqrt(o+s*s);n>1&&n<-1||(l=2*n*n*n-3*n*n+1,l>0&&(s=4*(t+i*e),v+=l*c[s+3],g+=l,c[s+3]<255&&(l=l*c[s+3]/250),f+=l*c[s],p+=l*c[s+1],m+=l*c[s+2],d+=l))}}u[r]=f/d,u[r+1]=p/d,u[r+2]=m/d,u[r+3]=v/g}return l}}t(xh,"type","Resize"),t(xh,"defaults",{resizeType:"hermite",scaleX:1,scaleY:1,lanczosLobes:3}),t(xh,"uniformLocations",["uDelta","uTaps"]),tt.setClass(xh);class Ch extends Va{getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform float uSaturation;\n  varying vec2 vTexCoord;\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    float rgMax = max(color.r, color.g);\n    float rgbMax = max(rgMax, color.b);\n    color.r += rgbMax != color.r ? (rgbMax - color.r) * uSaturation : 0.00;\n    color.g += rgbMax != color.g ? (rgbMax - color.g) * uSaturation : 0.00;\n    color.b += rgbMax != color.b ? (rgbMax - color.b) * uSaturation : 0.00;\n    gl_FragColor = color;\n  }\n"}applyTo2d(t){let{imageData:{data:e}}=t;const s=-this.saturation;for(let t=0;t<e.length;t+=4){const i=e[t],r=e[t+1],n=e[t+2],o=Math.max(i,r,n);e[t]+=o!==i?(o-i)*s:0,e[t+1]+=o!==r?(o-r)*s:0,e[t+2]+=o!==n?(o-n)*s:0}}sendUniformData(t,e){t.uniform1f(e.uSaturation,-this.saturation)}isNeutralState(){return 0===this.saturation}}t(Ch,"type","Saturation"),t(Ch,"defaults",{saturation:0}),t(Ch,"uniformLocations",["uSaturation"]),tt.setClass(Ch);class bh extends Va{getFragmentSource(){return"\n  precision highp float;\n  uniform sampler2D uTexture;\n  uniform float uVibrance;\n  varying vec2 vTexCoord;\n  void main() {\n    vec4 color = texture2D(uTexture, vTexCoord);\n    float max = max(color.r, max(color.g, color.b));\n    float avg = (color.r + color.g + color.b) / 3.0;\n    float amt = (abs(max - avg) * 2.0) * uVibrance;\n    color.r += max != color.r ? (max - color.r) * amt : 0.00;\n    color.g += max != color.g ? (max - color.g) * amt : 0.00;\n    color.b += max != color.b ? (max - color.b) * amt : 0.00;\n    gl_FragColor = color;\n  }\n"}applyTo2d(t){let{imageData:{data:e}}=t;const s=-this.vibrance;for(let t=0;t<e.length;t+=4){const i=e[t],r=e[t+1],n=e[t+2],o=Math.max(i,r,n),a=(i+r+n)/3,h=2*Math.abs(o-a)/255*s;e[t]+=o!==i?(o-i)*h:0,e[t+1]+=o!==r?(o-r)*h:0,e[t+2]+=o!==n?(o-n)*h:0}}sendUniformData(t,e){t.uniform1f(e.uVibrance,-this.vibrance)}isNeutralState(){return 0===this.vibrance}}t(bh,"type","Vibrance"),t(bh,"defaults",{vibrance:0}),t(bh,"uniformLocations",["uVibrance"]),tt.setClass(bh);var Sh=Object.freeze({__proto__:null,BaseFilter:Va,BlackWhite:nh,BlendColor:Ga,BlendImage:Ua,Blur:qa,Brightness:Ka,Brownie:$a,ColorMatrix:Qa,Composed:oh,Contrast:ah,Convolute:ch,Gamma:uh,Grayscale:gh,HueRotation:ph,Invert:mh,Kodachrome:eh,Noise:vh,Pixelate:yh,Polaroid:ih,RemoveColor:_h,Resize:xh,Saturation:Ch,Sepia:rh,Technicolor:sh,Vibrance:bh,Vintage:th});
//# sourceMappingURL=index.min.mjs.map


/***/ },

/***/ "./node_modules/nanoid/non-secure/index.js"
/*!*************************************************!*\
  !*** ./node_modules/nanoid/non-secure/index.js ***!
  \*************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   customAlphabet: () => (/* binding */ customAlphabet),
/* harmony export */   nanoid: () => (/* binding */ nanoid)
/* harmony export */ });
/* @ts-self-types="./index.d.ts" */
let urlAlphabet =
  'useandom-26T198340PX75pxJACKVERYMINDBUSHWOLF_GQZbfghjklqvwyzrict'
let customAlphabet = (alphabet, defaultSize = 21) => {
  return (size = defaultSize) => {
    let id = ''
    let i = size | 0
    while (i--) {
      id += alphabet[(Math.random() * alphabet.length) | 0]
    }
    return id
  }
}
let nanoid = (size = 21) => {
  let id = ''
  let i = size | 0
  while (i--) {
    id += urlAlphabet[(Math.random() * 64) | 0]
  }
  return id
}


/***/ },

/***/ "./node_modules/@uppy/core/package.json"
/*!**********************************************!*\
  !*** ./node_modules/@uppy/core/package.json ***!
  \**********************************************/
(module) {

"use strict";
module.exports = /*#__PURE__*/JSON.parse('{"name":"@uppy/core","description":"Core module for the extensible JavaScript file upload widget with support for drag&drop, resumable uploads, previews, restrictions, file processing/encoding, remote providers like Instagram, Dropbox, Google Drive, S3 and more :dog:","version":"4.5.3","license":"MIT","main":"lib/index.js","style":"dist/style.min.css","type":"module","sideEffects":["*.css"],"scripts":{"build":"tsc --build tsconfig.build.json","build:css":"sass --load-path=../../ src/style.scss dist/style.css && postcss dist/style.css -u cssnano -o dist/style.min.css","typecheck":"tsc --build","test":"vitest run --environment=jsdom --silent=\'passed-only\'"},"keywords":["file uploader","uppy","uppy-plugin"],"homepage":"https://uppy.io","bugs":{"url":"https://github.com/transloadit/uppy/issues"},"repository":{"type":"git","url":"git+https://github.com/transloadit/uppy.git"},"files":["src","lib","dist","CHANGELOG.md"],"dependencies":{"@transloadit/prettier-bytes":"^0.3.4","@uppy/store-default":"^4.3.2","@uppy/utils":"^6.2.2","lodash":"^4.17.21","mime-match":"^1.0.2","namespace-emitter":"^2.0.1","nanoid":"^5.0.9","preact":"^10.5.13"},"devDependencies":{"@types/deep-freeze":"^0","cssnano":"^7.0.7","deep-freeze":"^0.0.1","jsdom":"^26.1.0","postcss":"^8.5.6","postcss-cli":"^11.0.1","sass":"^1.89.2","typescript":"^5.8.3","vitest":"^3.2.4"}}');

/***/ },

/***/ "./node_modules/@uppy/drag-drop/package.json"
/*!***************************************************!*\
  !*** ./node_modules/@uppy/drag-drop/package.json ***!
  \***************************************************/
(module) {

"use strict";
module.exports = /*#__PURE__*/JSON.parse('{"name":"@uppy/drag-drop","description":"Droppable zone UI for Uppy. Drag and drop files into it to upload.","version":"4.2.2","license":"MIT","main":"lib/index.js","style":"dist/style.min.css","type":"module","scripts":{"build":"tsc --build tsconfig.build.json","build:css":"sass --load-path=../../ src/style.scss dist/style.css && postcss dist/style.css -u cssnano -o dist/style.min.css","typecheck":"tsc --build"},"keywords":["file uploader","uppy","uppy-plugin","drag-drop","drag","drop","dropzone","upload"],"homepage":"https://uppy.io","bugs":{"url":"https://github.com/transloadit/uppy/issues"},"repository":{"type":"git","url":"git+https://github.com/transloadit/uppy.git"},"files":["src","lib","dist","CHANGELOG.md"],"dependencies":{"@uppy/utils":"^6.2.2","preact":"^10.5.13"},"peerDependencies":{"@uppy/core":"^4.5.2"},"devDependencies":{"cssnano":"^7.0.7","postcss":"^8.5.6","postcss-cli":"^11.0.1","sass":"^1.89.2","typescript":"^5.8.3"}}');

/***/ },

/***/ "./node_modules/@uppy/store-default/package.json"
/*!*******************************************************!*\
  !*** ./node_modules/@uppy/store-default/package.json ***!
  \*******************************************************/
(module) {

"use strict";
module.exports = /*#__PURE__*/JSON.parse('{"name":"@uppy/store-default","description":"The default simple object-based store for Uppy.","version":"4.3.2","license":"MIT","main":"lib/index.js","type":"module","scripts":{"build":"tsc --build tsconfig.build.json","typecheck":"tsc --build","test":"vitest run --environment=jsdom --silent=\'passed-only\'"},"keywords":["file uploader","uppy","uppy-store"],"homepage":"https://uppy.io","bugs":{"url":"https://github.com/transloadit/uppy/issues"},"devDependencies":{"jsdom":"^26.1.0","typescript":"^5.8.3","vitest":"^3.2.4"},"repository":{"type":"git","url":"git+https://github.com/transloadit/uppy.git"},"files":["src","lib","dist","CHANGELOG.md"]}');

/***/ },

/***/ "./node_modules/@uppy/xhr-upload/package.json"
/*!****************************************************!*\
  !*** ./node_modules/@uppy/xhr-upload/package.json ***!
  \****************************************************/
(module) {

"use strict";
module.exports = /*#__PURE__*/JSON.parse('{"name":"@uppy/xhr-upload","description":"Plain and simple classic HTML multipart form uploads with Uppy, as well as uploads using the HTTP PUT method.","version":"4.4.2","license":"MIT","main":"lib/index.js","type":"module","scripts":{"build":"tsc --build tsconfig.build.json","typecheck":"tsc --build","test":"vitest run --environment=jsdom --silent=\'passed-only\'"},"keywords":["file uploader","xhr","xhr upload","XMLHttpRequest","ajax","fetch","uppy","uppy-plugin"],"homepage":"https://uppy.io","bugs":{"url":"https://github.com/transloadit/uppy/issues"},"repository":{"type":"git","url":"git+https://github.com/transloadit/uppy.git"},"files":["src","lib","dist","CHANGELOG.md"],"dependencies":{"@uppy/companion-client":"^4.5.2","@uppy/utils":"^6.2.2"},"devDependencies":{"@uppy/core":"^4.5.2","jsdom":"^26.1.0","nock":"^13.1.0","typescript":"^5.8.3","vitest":"^3.2.4"},"peerDependencies":{"@uppy/core":"^4.5.2"}}');

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
/*!****************************************!*\
  !*** ./src/frontend/customiser-app.js ***!
  \****************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var fabric__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! fabric */ "./node_modules/fabric/dist/index.min.mjs");
/* harmony import */ var _uppy_core__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @uppy/core */ "./node_modules/@uppy/core/lib/Uppy.js");
/* harmony import */ var _uppy_drag_drop__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @uppy/drag-drop */ "./node_modules/@uppy/drag-drop/lib/index.js");
/* harmony import */ var _uppy_xhr_upload__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @uppy/xhr-upload */ "./node_modules/@uppy/xhr-upload/lib/index.js");
/* harmony import */ var _uppy_core_dist_style_min_css__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @uppy/core/dist/style.min.css */ "./node_modules/@uppy/core/dist/style.min.css");
/* harmony import */ var _uppy_drag_drop_dist_style_min_css__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @uppy/drag-drop/dist/style.min.css */ "./node_modules/@uppy/drag-drop/dist/style.min.css");
/* harmony import */ var _customiser_app_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./customiser-app.scss */ "./src/frontend/customiser-app.scss");
/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 6.x  |  Uploads: Uppy 4.x
 *
 * @package OverCustomise
 */









// ── Boot ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  const data = window.ocCustomiserData;
  if (!data || !data.areas?.length) return;
  new OCCustomiser(data).init();
});

// ── Main class ─────────────────────────────────────────────────────────────────

class OCCustomiser {
  constructor(data) {
    this.data = data;
    this.areas = data.areas || [];
    this.fonts = data.fonts || [];
    this.activeArea = 0;

    // Deep-clone mutable per-layer inputs; keys are integer layer IDs.
    this.inputs = {};
    Object.entries(data.layerInputs || {}).forEach(([k, v]) => {
      this.inputs[parseInt(k, 10)] = {
        ...v
      };
    });
    this.editMode = !!(data.editMode && data.cartKey);
    this.cartKey = this.editMode ? data.cartKey : '';
    this.canvases = {}; // areaIndex → Fabric StaticCanvas
    this.fontCache = {}; // fontName  → load Promise
    this.galleryImg = null; // the main <img> in the product gallery
    this._previewUrl = null; // saved preview URL (set just before cart submit)
    this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
    this._mobilePreviewVisible = false;
    this.spotifyValidateTimers = {};
    this.spotifyValidateTokens = {};
    this.preflightRoot = null;
    this.clipartByGroup = {};
    this.clipartSearchTimers = {};
    this.clipartSearchTerms = {};
    this.clipartCategoryFilters = {};
    this.spotifyModalCloseTimer = null;
    if (this.editMode) {
      Object.entries(data.layerInputs || {}).forEach(([k, v]) => {
        const key = parseInt(k, 10);
        if (this.inputs[key] && typeof v === 'object' && v !== null) {
          Object.assign(this.inputs[key], v);
        }
      });
    }
    for (const [lidStr, items] of Object.entries(data.clipartByLayer || {})) {
      const lid = parseInt(lidStr, 10);
      for (const item of items) {
        for (const gn of item.groupNames || []) {
          if (!this.clipartByGroup[gn]) {
            this.clipartByGroup[gn] = {};
          }
          this.clipartByGroup[gn][lid] = item;
        }
      }
    }
  }
  restHeaders(extra = {}) {
    const headers = {
      'X-WP-Nonce': this.data.uploadNonce,
      ...extra
    };
    if (this.data.requestToken) {
      headers['X-OC-Token'] = this.data.requestToken;
    }
    return headers;
  }
  uploadEndpoint(uploadUrl, layerId) {
    const params = new URLSearchParams({
      layer_id: String(layerId)
    });
    if (this.data.uploadNonce) params.set('_wpnonce', this.data.uploadNonce);
    if (this.data.requestToken) params.set('oc_token', this.data.requestToken);
    return uploadUrl + (uploadUrl.includes('?') ? '&' : '?') + params.toString();
  }

  // ── Init ───────────────────────────────────────────────────────────────────

  init() {
    this.findGalleryImage();
    this.preflightRoot = document.getElementById('oc-preflight-messages');

    // Seed configured/default fonts for text layers so they render immediately.
    if (this.fonts.length) {
      const firstFont = this.fonts[0];
      this.areas.forEach(area => {
        (area.layers || []).forEach(layer => {
          if (layer.type === 'text' || layer.type === 'textarea') {
            const inp = this.inputs[layer.id];
            if (inp && !inp.fontId) inp.fontId = layer.settings?.default_font_id || firstFont.id;
          }
        });
      });
    }

    // Wire up controls IMMEDIATELY — don't block on canvas.
    this.setupInputListeners();
    this.setupMobilePreview();
    this.setupUploadZones();
    if (this.editMode) this.updateInputsFromDOM();
    this.setupFormSubmit();
    this.updateHiddenField();

    // Canvas init runs in background; calls redraw() when done.
    this.initAllCanvases();
  }

  // ── Gallery: find the <img> and store a reference ─────────────────────────

  findGalleryImage() {
    const SELECTORS = [
    // True Video Product Gallery (Swiper): prefer active non-video slide.
    '.tvpg-main-slider .swiper-slide-active:not(.tvpg-video-slide) .woocommerce-product-gallery__image img', '.tvpg-main-slider .swiper-slide-active .woocommerce-product-gallery__image img', '.tvpg-main-slider .swiper-slide:not(.tvpg-video-slide) .woocommerce-product-gallery__image img',
    // Flatsome theme
    '.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image.is-selected img', '.product-gallery-slider .flickity-slider .slide.is-selected img', '.product-gallery-slider .is-selected img', '.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image:first-child img', '.product-gallery-slider .flickity-slider .slide:first-child img', '.product-gallery .woocommerce-product-gallery__image:first-child img', '.product-images .woocommerce-product-gallery__image:first-child a img', '.product-image-wrap .woocommerce-product-gallery__image:first-child img',
    // WC Blocks
    '.wp-block-woocommerce-product-image-gallery .woocommerce-product-gallery__image:first-child img',
    // Default WC / Storefront
    '.woocommerce-product-gallery__image:first-child a img', '.woocommerce-product-gallery__image:first-child img',
    // Broad fallback
    '.woocommerce-product-gallery .wp-post-image', '.wp-post-image'];
    for (const sel of SELECTORS) {
      const img = document.querySelector(sel);
      if (img) {
        this.galleryImg = img;
        return;
      }
    }
    this.galleryImg = null;
  }
  applyPreviewToImage(img, dataUrl) {
    if (!img) return;
    img.src = dataUrl;
    img.srcset = '';
    img.sizes = '';

    // Update zoom / lightbox href if wrapped in <a>.
    const a = img.closest('a');
    if (a) {
      a.href = dataUrl;
      a.setAttribute('data-src', dataUrl);
    }

    // WooCommerce zoom/lightbox compatibility attributes.
    img.setAttribute('data-large_image', dataUrl);
    img.setAttribute('data-large-image', dataUrl);
    img.setAttribute('data-src', dataUrl);
    img.setAttribute('data-lazy-src', dataUrl);
    img.setAttribute('data-zoom-image', dataUrl);
    img.removeAttribute('data-srcset');
    img.removeAttribute('data-lazy-srcset');
    img.removeAttribute('data-o_srcset');
    img.removeAttribute('data-o_src');
    const galleryItem = img.closest('.woocommerce-product-gallery__image, .product-gallery-slider .slide');
    if (galleryItem) {
      galleryItem.setAttribute('data-thumb', dataUrl);
    }
  }
  refreshFlatsomeGallery() {
    const slider = document.querySelector('.product-gallery-slider');
    if (!slider) return;
    const flickity = slider.flickity || window.jQuery?.(slider).data('flickity');
    flickity?.reloadCells?.();
    flickity?.resize?.();
  }
  getFlickityInstance(slider) {
    if (!slider) return null;
    return slider.flickity || window.jQuery?.(slider).data('flickity') || null;
  }
  applyFlatsomeOverlayPreview(dataUrl) {
    const slider = document.querySelector('.product-gallery-slider');
    if (!slider) return false;
    let flickity = this.getFlickityInstance(slider);
    let previewSlide = slider.querySelector('.oc-live-preview-slide');
    if (!previewSlide) {
      previewSlide = document.createElement('div');
      previewSlide.className = 'woocommerce-product-gallery__image slide oc-live-preview-slide';
      previewSlide.innerHTML = '<a href="#">' + '<img class="oc-live-preview-image wp-post-image" alt="Custom preview">' + '</a>';
      if (flickity?.append) {
        flickity.append(previewSlide);
      } else {
        slider.appendChild(previewSlide);
      }
    }
    const previewImg = previewSlide.querySelector('img.oc-live-preview-image');
    if (previewImg) {
      this.applyPreviewToImage(previewImg, dataUrl);
    }
    previewSlide.setAttribute('data-thumb', dataUrl);
    previewSlide.querySelector('a')?.setAttribute('href', dataUrl);
    flickity = this.getFlickityInstance(slider);
    if (flickity) {
      flickity.reloadCells?.();
      flickity.resize?.();
      const previewIndex = (flickity.cells || []).findIndex(cell => cell.element === previewSlide);
      if (previewIndex >= 0) {
        flickity.select?.(previewIndex, false, true);
      }
    }
    return true;
  }
  setPanelPreviewHandoff(isActive) {
    const panel = document.getElementById('oc-customiser-panel');
    if (panel) {
      panel.classList.toggle('oc-gallery-preview-active', isActive);
    }
  }
  mountPreviewInGallery() {
    const canvasWrap = document.getElementById('oc-canvas-wrap');
    if (!canvasWrap) return false;
    const gallery = document.querySelector('.product-gallery, .product-images, .woocommerce-product-gallery, .product .images');
    if (!gallery) {
      canvasWrap.classList.add('oc-preview-visible');
      return false;
    }
    if (canvasWrap.parentElement !== gallery) {
      gallery.prepend(canvasWrap);
    }
    canvasWrap.classList.add('oc-gallery-mounted-preview', 'oc-preview-visible');
    return true;
  }
  applyTVPGOverlayPreview(dataUrl) {
    const mainSliderEl = document.querySelector('.tvpg-main-slider');
    const mainWrapper = mainSliderEl?.querySelector('.swiper-wrapper');
    if (!mainSliderEl || !mainWrapper) return false;
    let mainPreviewSlide = mainWrapper.querySelector('.swiper-slide.oc-live-preview-slide');
    if (!mainPreviewSlide) {
      mainPreviewSlide = document.createElement('div');
      mainPreviewSlide.className = 'swiper-slide oc-live-preview-slide';
      mainPreviewSlide.innerHTML = '<div class="woocommerce-product-gallery__image">' + '<img class="oc-live-preview-image" alt="Custom preview">' + '</div>';
      mainWrapper.appendChild(mainPreviewSlide);
    }
    const mainImg = mainPreviewSlide.querySelector('img.oc-live-preview-image');
    if (mainImg) {
      this.applyPreviewToImage(mainImg, dataUrl);
    }
    const thumbSliderEl = document.querySelector('.tvpg-thumb-slider');
    const thumbWrapper = thumbSliderEl?.querySelector('.swiper-wrapper');
    if (thumbWrapper) {
      let thumbPreviewSlide = thumbWrapper.querySelector('.swiper-slide.oc-live-preview-thumb-slide');
      if (!thumbPreviewSlide) {
        thumbPreviewSlide = document.createElement('div');
        thumbPreviewSlide.className = 'swiper-slide oc-live-preview-thumb-slide';
        thumbPreviewSlide.innerHTML = '<img class="oc-live-preview-thumb-image" alt="Custom preview thumbnail">';
        thumbWrapper.appendChild(thumbPreviewSlide);
      }
      const thumbImg = thumbPreviewSlide.querySelector('img.oc-live-preview-thumb-image');
      if (thumbImg) {
        this.applyPreviewToImage(thumbImg, dataUrl);
      }
    }

    // Swiper attaches instances to the root element; update so the new last slide is navigable.
    const mainSwiper = mainSliderEl.swiper;
    const thumbSwiper = thumbSliderEl?.swiper;
    mainSwiper?.update?.();
    thumbSwiper?.update?.();
    if (this._focusPreviewSlide && mainSwiper?.slides?.length) {
      const lastIndex = mainSwiper.slides.length - 1;
      mainSwiper.slideTo(lastIndex);
      thumbSwiper?.slideTo?.(lastIndex);
    }
    this._focusPreviewSlide = false;
    return true;
  }
  pushToGallery(canvas) {
    this.findGalleryImage();
    let dataUrl;
    try {
      dataUrl = canvas.toDataURL({
        format: 'jpeg',
        quality: 0.92
      });
    } catch (e) {
      console.warn('[OC] toDataURL failed — image may be cross-origin:', e.message);
      return;
    }
    const previewImg = document.getElementById('oc-canvas-preview');
    if (previewImg) {
      previewImg.src = dataUrl;
      previewImg.srcset = '';
    }
    if (this.applyTVPGOverlayPreview(dataUrl)) {
      this.setPanelPreviewHandoff(true);
      this._focusPreviewSlide = false;
      return;
    }
    if (this.applyFlatsomeOverlayPreview(dataUrl)) {
      this.setPanelPreviewHandoff(true);
      this._focusPreviewSlide = false;
      return;
    }
    const targets = new Set();
    if (this.galleryImg) {
      targets.add(this.galleryImg);
    }
    ['.tvpg-main-slider .swiper-slide .woocommerce-product-gallery__image img', '.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image img', '.product-gallery-slider .flickity-slider .slide img', '.product-gallery-slider .slide img', '.product-gallery-slider img', '.product-thumbnails img', '.product-gallery .woocommerce-product-gallery__image img', '.product-images .woocommerce-product-gallery__image img', '.product-image-wrap .woocommerce-product-gallery__image img', '.woocommerce-product-gallery .woocommerce-product-gallery__image img'].forEach(selector => {
      document.querySelectorAll(selector).forEach(img => targets.add(img));
    });
    const applyTargets = () => targets.forEach(img => this.applyPreviewToImage(img, dataUrl));
    applyTargets();
    if (document.querySelector('.product-gallery-slider')) {
      this.refreshFlatsomeGallery();
      requestAnimationFrame(applyTargets);
      setTimeout(applyTargets, 250);
    }
    this.setPanelPreviewHandoff(targets.size > 0 || this.mountPreviewInGallery());
    this._focusPreviewSlide = false;
  }
  requestPreviewFocus() {
    this._focusPreviewSlide = true;
  }

  // ── Canvas initialisation ──────────────────────────────────────────────────

  async initAllCanvases() {
    for (let i = 0; i < this.areas.length; i++) {
      const el = document.getElementById(`oc-canvas-${i}`);
      if (el) {
        await this.initCanvas(el, i);
        // Full redraw AFTER init picks up any text the user already typed.
        await this.redraw(i);
      }
    }
  }
  async initCanvas(canvasEl, areaIndex) {
    const area = this.areas[areaIndex];

    // Use mockup natural width when available (works even when canvas is visually hidden).
    // Cap at 1200px for performance; fall back to element width or 600px.
    await new Promise(r => requestAnimationFrame(r));
    const displayW = area.mockupW ? Math.min(area.mockupW, 1200) : Math.max(canvasEl.parentElement?.offsetWidth || 0, 600);
    if (!area.mockupUrl) {
      this.canvases[areaIndex] = this.blankCanvas(canvasEl, displayW, 240, 'No mockup set. Add one in the Design Editor.');
      return;
    }
    let mockupImg;
    try {
      // Do NOT use crossOrigin:'anonymous' — WordPress uploads are same-origin
      // and CORS headers aren't sent, which would taint the canvas and break toDataURL.
      mockupImg = await Promise.race([fabric__WEBPACK_IMPORTED_MODULE_0__.FabricImage.fromURL(area.mockupUrl), new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 10000))]);
    } catch (e) {
      console.warn('[OC] Mockup failed to load:', area.mockupUrl, e.message);
      this.canvases[areaIndex] = this.blankCanvas(canvasEl, displayW, 240, 'Mockup image could not load.');
      return;
    }
    const scaleX = displayW / (area.mockupW || mockupImg.width || 1);
    const displayH = Math.round((area.mockupH || mockupImg.height) * scaleX);
    const canvas = new fabric__WEBPACK_IMPORTED_MODULE_0__.StaticCanvas(canvasEl, {
      width: displayW,
      height: displayH
    });
    mockupImg.set({
      left: 0,
      top: 0,
      scaleX,
      scaleY: scaleX,
      selectable: false
    });
    canvas.add(mockupImg);

    // Dashed print-bounds guide.
    const b = area.bounds;
    if (b && b.w > 0) {
      canvas.add(new fabric__WEBPACK_IMPORTED_MODULE_0__.Rect({
        left: (b.x + b.w / 2) * scaleX,
        top: (b.y + b.h / 2) * scaleX,
        originX: 'center',
        originY: 'center',
        angle: Number(b.rotation) || 0,
        width: b.w * scaleX,
        height: b.h * scaleX,
        fill: 'rgba(255,255,255,0.05)',
        stroke: 'rgba(255,255,255,0.7)',
        strokeWidth: 1.5,
        strokeDashArray: [5, 4],
        selectable: false,
        evented: false
      }));
    }
    canvas._ocScaleX = scaleX;
    canvas._ocArea = area;
    canvas.renderAll();
    this.canvases[areaIndex] = canvas;
    console.log(`[OC] Canvas ${areaIndex} ready — ${displayW}x${displayH}, scale=${scaleX.toFixed(3)}`);
  }
  blankCanvas(el, w, h, msg) {
    const c = new fabric__WEBPACK_IMPORTED_MODULE_0__.StaticCanvas(el, {
      width: w,
      height: h,
      backgroundColor: '#f0f0f0'
    });
    const t = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText(msg, {
      left: w / 2,
      top: h / 2,
      originX: 'center',
      originY: 'center',
      fontSize: 12,
      fill: '#888',
      fontFamily: 'sans-serif',
      textAlign: 'center',
      selectable: false
    });
    c.add(t);
    c.renderAll();
    c._ocScaleX = 1;
    return c;
  }

  // ── Redraw ──────────────────────────────────────────────────────────────────

  scheduleRedraw() {
    clearTimeout(this._redrawTimer);
    this._redrawTimer = setTimeout(() => this.redraw(this.activeArea), 120);
  }
  async redraw(areaIndex) {
    const canvas = this.canvases[areaIndex];
    if (!canvas) return; // canvas not ready yet — will redraw after initCanvas

    // Remove previously added content objects.
    [...canvas.getObjects()].filter(o => o._ocContent === true).forEach(o => canvas.remove(o));
    const area = this.areas[areaIndex];
    for (const layer of area?.layers ?? []) {
      // PHP already filters to visible-only layers — no client-side check needed.
      await this.renderLayer(canvas, layer, this.inputs[layer.id] || {}, area);
    }
    canvas.renderAll();
    if (areaIndex === this.activeArea) this.pushToGallery(canvas);
  }
  async renderLayer(canvas, layer, input, area) {
    const scale = canvas._ocScaleX ?? 1;
    const bounds = area?.bounds || {};
    const rotation = Number(bounds.rotation) || 0;
    const center = this.rotatedLayerCenter(layer, bounds, rotation);
    const lx = (center.x - layer.w / 2) * scale;
    const ly = (center.y - layer.h / 2) * scale;
    const lw = Math.max(layer.w * scale, 10);
    const lh = Math.max(layer.h * scale, 10);
    const lcX = center.x * scale;
    const lcY = center.y * scale;
    const isEngraving = area?.printMethod === 'engraving';
    const engravingPalette = this.engravingPalette();
    const fontLimit = value => Math.max(0, parseInt(value, 10) || 0);
    const clampFontSize = (size, settings) => {
      const min = fontLimit(settings?.min_font_size) * scale;
      const max = fontLimit(settings?.max_font_size) * scale;
      if (max && (!min || min <= max)) size = Math.min(size, max);
      if (min) size = Math.max(size, min);
      return size;
    };
    switch (layer.type) {
      case 'text':
      case 'textarea':
        {
          const raw = (input.value || '').trim() || (layer.settings?.default_text || '').trim();
          if (!raw) break;
          let font = this.fonts.find(f => f.id === (input.fontId || 0));
          // Engraving uses a fixed silver tone instead of a customer-selected colour.
          const color = isEngraving ? engravingPalette.text : input.colorHex || layer.settings?.default_color || '#000000';
          const align = layer.settings?.alignment || 'center';
          if (font) {
            try {
              await this.loadFont(font);
            } catch (err) {
              console.warn('[OC] Font load failed, falling back to sans-serif:', err);
              font = null;
            }
          }
          const minFontSize = fontLimit(layer.settings?.min_font_size) * scale;
          const configuredFontSize = input.fontSize || layer.settings?.default_font_size;
          let fontSize = configuredFontSize ? clampFontSize(Math.max(1, parseInt(configuredFontSize, 10)) * scale, layer.settings) : clampFontSize(Math.max(10, Math.round(lh * 0.42)), layer.settings);
          const obj = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText(raw, {
            left: lcX,
            top: lcY,
            originX: 'center',
            originY: 'center',
            width: lw,
            angle: rotation,
            fontFamily: font?.name || 'sans-serif',
            fontSize,
            fill: color,
            textAlign: align,
            selectable: false,
            evented: false
          });
          obj._ocContent = true; // tag after creation

          if (isEngraving) {
            // Fake etched depth: subtle light highlight below + soft dark shadow above.
            obj.set({
              opacity: 0.92,
              shadow: new fabric__WEBPACK_IMPORTED_MODULE_0__.Shadow({
                color: engravingPalette.highlight,
                offsetX: 0,
                offsetY: 1,
                blur: 1
              })
            });
          }
          while (obj.width > lw && fontSize > Math.max(8, minFontSize)) {
            fontSize -= 1;
            obj.set({
              fontSize
            });
          }
          canvas.add(obj);
          break;
        }
      case 'image':
        if (input.attachmentUrl) await this.renderFabricImg(canvas, input.attachmentUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette);
        break;
      case 'clipart':
        if (input.clipartUrl) await this.renderFabricImg(canvas, input.clipartUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette);
        break;
      case 'lineart':
        {
          const lineartColor = isEngraving ? engravingPalette.text : String(input.colorHex || '').trim();
          if (!lineartColor) break;
          const r = new fabric__WEBPACK_IMPORTED_MODULE_0__.Rect({
            left: lcX,
            top: lcY,
            originX: 'center',
            originY: 'center',
            angle: rotation,
            width: lw,
            height: lh,
            fill: lineartColor,
            opacity: 0.6,
            selectable: false,
            evented: false
          });
          r._ocContent = true;
          canvas.add(r);
          break;
        }
      case 'spotify':
        {
          const val = (input.value || '').trim();
          if (!val) break;
          if (input.spotifyStatus === 'invalid_format' || input.spotifyStatus === 'playlist_private_or_invalid' || input.spotifyStatus === 'invalid_or_unavailable') {
            const invalidText = input.spotifyStatus === 'playlist_private_or_invalid' ? 'Private / invalid Spotify playlist' : 'Invalid Spotify link';
            const invalidObj = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText(invalidText, {
              left: lcX,
              top: lcY,
              originX: 'center',
              originY: 'center',
              angle: rotation,
              fontFamily: 'monospace',
              fontSize: Math.max(9, Math.round(lh * 0.17)),
              fill: '#b32d2e',
              textAlign: 'center',
              selectable: false,
              evented: false
            });
            invalidObj._ocContent = true;
            canvas.add(invalidObj);
            break;
          }
          const spotifyCodeUrl = this.buildSpotifyCodeUrl(input.spotifyUri || val, isEngraving, engravingPalette);
          if (spotifyCodeUrl) {
            // Try CORS-safe load first; if Spotify CDN blocks CORS for this origin,
            // retry without crossOrigin so users still see the scannable in live preview.
            let rendered = await this.renderFabricImg(canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, 'anonymous', true, rotation, engravingPalette);
            if (!rendered) {
              rendered = await this.renderFabricImg(canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, '', true, rotation, engravingPalette);
            }
            if (rendered) break;
          }
          const fallback = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText('\u266b Spotify code unavailable', {
            left: lcX,
            top: lcY,
            originX: 'center',
            originY: 'center',
            angle: rotation,
            fontFamily: 'monospace',
            fontSize: Math.max(9, Math.round(lh * 0.22)),
            fill: '#666666',
            textAlign: 'center',
            selectable: false,
            evented: false
          });
          fallback._ocContent = true;
          canvas.add(fallback);
          break;
        }
    }
  }
  rotatedLayerCenter(layer, bounds, rotation) {
    let x = layer.x + layer.w / 2;
    let y = layer.y + layer.h / 2;
    if (!bounds?.w || !rotation) return {
      x,
      y
    };
    const cx = bounds.x + bounds.w / 2;
    const cy = bounds.y + bounds.h / 2;
    const rad = rotation * Math.PI / 180;
    const dx = x - cx;
    const dy = y - cy;
    x = cx + dx * Math.cos(rad) - dy * Math.sin(rad);
    y = cy + dx * Math.sin(rad) + dy * Math.cos(rad);
    return {
      x,
      y
    };
  }
  extractSpotifyUri(inputValue) {
    const raw = String(inputValue || '').trim();
    if (!raw) return '';
    if (/^spotify:[a-z]+:[A-Za-z0-9]+$/i.test(raw)) return raw;
    let parsed;
    try {
      parsed = new URL(raw);
    } catch (e) {
      return '';
    }
    const host = parsed.hostname.toLowerCase();
    if (host !== 'open.spotify.com' && host !== 'play.spotify.com') return '';
    const parts = parsed.pathname.split('/').filter(Boolean).filter(p => !/^intl-[a-z]{2}$/i.test(p));
    if (!parts.length) return '';
    const validTypes = ['track', 'album', 'artist', 'playlist', 'episode', 'show'];
    const typeIndex = parts.findIndex(p => validTypes.includes(p));
    if (typeIndex < 0 || !parts[typeIndex + 1]) return '';
    const type = parts[typeIndex];
    const id = parts[typeIndex + 1];
    if (!/^[A-Za-z0-9]+$/.test(id)) return '';
    return `spotify:${type}:${id}`;
  }
  engravingPalette() {
    return {
      text: '#dadad6',
      bg: 'ECEFF1',
      highlight: 'rgba(255,255,255,0.42)',
      brightness: -0.28,
      contrast: 0.18,
      opacity: 0.9
    };
  }
  buildSpotifyCodeUrl(inputValue, isEngraving, engravingPalette = null) {
    const spotifyUri = this.extractSpotifyUri(inputValue);
    if (!spotifyUri) return '';

    // Official Spotify scannable-code endpoint.
    // Endpoint shape:
    // /uri/plain/{format}/{background-hex}/{bar-colour}/{size}/{spotify-uri}
    // We request SVG and then strip white in-canvas for transparent compositing.
    const format = 'svg';
    const bgHex = isEngraving ? engravingPalette?.bg || 'F5F2EF' : 'FFFFFF';
    const bar = isEngraving ? 'black' : 'black';
    const size = 640;
    return `https://scannables.scdn.co/uri/plain/${format}/${bgHex}/${bar}/${size}/${spotifyUri}`;
  }
  async renderFabricImg(canvas, url, x, y, w, h, isEngraving = false, crossOrigin = 'anonymous', makeWhiteTransparent = false, angle = 0, engravingPalette = null) {
    try {
      const imgLoadOpts = crossOrigin ? {
        crossOrigin
      } : {};
      const img = await fabric__WEBPACK_IMPORTED_MODULE_0__.FabricImage.fromURL(url, imgLoadOpts);
      if (!img || !img.width) {
        console.warn('[OC] Image failed to load or has zero dimensions:', url);
        return false;
      }
      const s = Math.min(w / img.width, h / img.height);
      img.set({
        left: x + w / 2,
        top: y + h / 2,
        originX: 'center',
        originY: 'center',
        scaleX: s,
        scaleY: s,
        angle,
        selectable: false,
        evented: false
      });
      const filters = [];
      if (makeWhiteTransparent) {
        filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.RemoveColor({
          color: '#FFFFFF',
          distance: 0.1
        }));
      }
      if (isEngraving) {
        const palette = engravingPalette || this.engravingPalette();
        filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Grayscale(), new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Brightness({
          brightness: palette.brightness
        }), new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Contrast({
          contrast: palette.contrast
        }));
      }
      if (filters.length) {
        img.filters = filters;
        img.applyFilters();
      }
      if (isEngraving) {
        const palette = engravingPalette || this.engravingPalette();
        img.set({
          opacity: palette.opacity
        });
      }
      img._ocContent = true;
      canvas.add(img);
      return true;
    } catch (e) {
      console.warn('[OC] renderFabricImg error:', e, 'URL:', url);
      return false;
    }
  }
  async loadFont(font) {
    if (!font?.name || !font?.url) return;
    if (this.fontCache[font.name]) return this.fontCache[font.name];
    const ff = new FontFace(font.name, `url('${font.url}')`, {
      weight: font.weight || 'normal',
      style: font.style || 'normal'
    });
    this.fontCache[font.name] = ff.load().then(f => document.fonts.add(f)).catch(err => {
      delete this.fontCache[font.name];
      console.warn('[OC] Font load failed:', err);
      throw err;
    });
    return this.fontCache[font.name];
  }

  // ── Input listeners ─────────────────────────────────────────────────────────

  setupInputListeners() {
    // Area tabs
    const areaTabs = Array.from(document.querySelectorAll('.oc-area-tab'));
    areaTabs.forEach(btn => {
      btn.addEventListener('click', () => this.switchArea(parseInt(btn.dataset.areaIndex, 10)));
      btn.addEventListener('touchend', e => {
        e.preventDefault();
        this.switchArea(parseInt(btn.dataset.areaIndex, 10));
      }, {
        passive: false
      });
      btn.addEventListener('keydown', e => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
        e.preventDefault();
        const currentIndex = areaTabs.indexOf(btn);
        let nextIndex = currentIndex;
        if (e.key === 'ArrowLeft') nextIndex = Math.max(0, currentIndex - 1);
        if (e.key === 'ArrowRight') nextIndex = Math.min(areaTabs.length - 1, currentIndex + 1);
        if (e.key === 'Home') nextIndex = 0;
        if (e.key === 'End') nextIndex = areaTabs.length - 1;
        areaTabs[nextIndex]?.focus();
        this.switchArea(parseInt(areaTabs[nextIndex]?.dataset.areaIndex || '0', 10));
      });
    });

    // Text / textarea
    document.querySelectorAll('[data-oc-layer-text]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerText, 10);
      const counter = el.parentElement?.querySelector(`.oc-char-counter[data-oc-char-counter="${lid}"]`);
      const updateCounter = () => {
        if (!counter) return;
        const limit = parseInt(counter.dataset.charLimit, 10) || 0;
        if (limit === 0) {
          counter.style.display = 'none';
          return;
        }
        const current = el.value.length;
        counter.textContent = `${current} / ${limit}`;
        counter.classList.remove('--under', '--near', '--over');
        const pct = current / limit;
        if (pct > 0.95) counter.classList.add('--over');else if (pct >= 0.80) counter.classList.add('--near');else counter.classList.add('--under');
        counter.style.display = '';
      };
      updateCounter();
      el.addEventListener('input', () => {
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].value = el.value;
        updateCounter();
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
      });
    });

    // Spotify validation (invalid format / private playlist / unavailable).
    document.querySelectorAll('[data-oc-layer-spotify]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerSpotify, 10);
      if (!lid) return;
      el.addEventListener('input', () => {
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].value = el.value;
        this.inputs[lid].spotifyStatus = '';
        this.inputs[lid].spotifyUri = '';
        this.setSpotifyError(lid, '', el);
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
        clearTimeout(this.spotifyValidateTimers[lid]);
        this.spotifyValidateTimers[lid] = setTimeout(() => {
          this.validateSpotifyLayer(lid, el.value, el);
        }, 450);
      });
      el.addEventListener('blur', () => {
        this.validateSpotifyLayer(lid, el.value, el);
      });
    });

    // Help tooltips: tap to toggle on touch devices, close on outside tap.
    const closeHelpTooltips = () => {
      document.querySelectorAll('.oc-help-tooltip.oc-open, .oc-spotify-help.oc-open').forEach(help => {
        help.classList.remove('oc-open');
        help.querySelector('.oc-help-toggle, .oc-spotify-help-toggle')?.setAttribute('aria-expanded', 'false');
      });
    };
    document.querySelectorAll('.oc-help-toggle:not(.oc-spotify-modal-trigger), .oc-spotify-help-toggle').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        const help = btn.closest('.oc-help-tooltip, .oc-spotify-help');
        if (!help) return;
        const willOpen = !help.classList.contains('oc-open');
        closeHelpTooltips();
        if (willOpen) {
          help.classList.add('oc-open');
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });
    document.addEventListener('click', e => {
      if (!e.target.closest('.oc-help-tooltip, .oc-spotify-help')) closeHelpTooltips();
    });
    this.setupSpotifyModal();

    // Font selects — also reflect the picked font in the closed select.
    const reflectFontOnSelect = el => {
      const opt = el.options[el.selectedIndex];
      const fam = opt?.style?.fontFamily || '';
      if (fam) el.style.fontFamily = fam;
    };
    document.querySelectorAll('[data-oc-layer-font]').forEach(el => {
      reflectFontOnSelect(el);
      const lid = parseInt(el.dataset.ocLayerFont, 10);
      el.addEventListener('change', async () => {
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].fontId = parseInt(el.value, 10);
        const font = this.fonts.find(f => f.id === this.inputs[lid].fontId);
        if (font) {
          try {
            await this.loadFont(font);
          } catch (err) {
            console.warn('[OC] Font load failed:', err);
          }
        }
        reflectFontOnSelect(el);
        const preview = document.querySelector(`.oc-font-preview[data-oc-font-preview="${lid}"]`);
        if (preview && font) preview.style.fontFamily = font.name;
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
      });
    });

    // Font size
    document.querySelectorAll('[data-oc-layer-font-size]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerFontSize, 10);
      el.addEventListener('input', () => {
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].fontSize = Math.max(1, parseInt(el.value, 10) || 1);
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
      });
    });

    // Colour swatches
    document.querySelectorAll('[data-oc-layer-swatch]').forEach(btn => {
      btn.addEventListener('click', () => {
        const lid = parseInt(btn.dataset.ocLayerSwatch, 10);
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].colorHex = btn.dataset.hex;
        btn.closest('.oc-colour-swatches')?.querySelectorAll('.oc-colour-swatch').forEach(s => {
          const isSelected = s === btn;
          s.classList.toggle('oc-selected', isSelected);
          s.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
      });
    });

    // Free colour picker
    document.querySelectorAll('[data-oc-layer-color]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerColor, 10);
      el.addEventListener('input', () => {
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].colorHex = el.value;
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
      });
    });

    // Clipart items
    document.querySelectorAll('[data-oc-layer-clipart]').forEach(btn => {
      btn.addEventListener('click', () => {
        const lid = parseInt(btn.dataset.ocLayerClipart, 10);
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].clipartId = parseInt(btn.dataset.ocClipart, 10);
        this.inputs[lid].clipartUrl = btn.dataset.ocClipartUrl;
        btn.closest('.oc-clipart-grid')?.querySelectorAll('.oc-clipart-item').forEach(i => {
          const isSelected = i === btn;
          i.classList.toggle('oc-selected', isSelected);
          i.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
      });
    });

    // Clipart search (debounced 200ms)
    document.querySelectorAll('[data-oc-clipart-search]').forEach(input => {
      const lid = parseInt(input.dataset.ocClipartSearch, 10);
      this.clipartSearchTerms[lid] = '';
      input.addEventListener('input', () => {
        this.clipartSearchTerms[lid] = input.value;
        clearTimeout(this.clipartSearchTimers[lid]);
        this.clipartSearchTimers[lid] = setTimeout(() => {
          this.filterClipart(lid);
        }, 200);
      });
    });

    // Clipart category filter
    document.querySelectorAll('[data-oc-clipart-category]').forEach(select => {
      const lid = parseInt(select.dataset.ocClipartCategory, 10);
      this.clipartCategoryFilters[lid] = '';
      select.addEventListener('change', () => {
        this.clipartCategoryFilters[lid] = select.value;
        this.filterClipart(lid);
      });
    });

    // Remove uploaded image
    document.querySelectorAll('[data-oc-remove-image]').forEach(btn => {
      btn.addEventListener('click', () => {
        const lid = parseInt(btn.dataset.ocRemoveImage, 10);
        const zoneEl = btn.closest('.oc-artwork-wrap')?.querySelector(`[data-oc-upload-zone="${lid}"]`);
        if (zoneEl) this.clearUploadedImage(lid, zoneEl);
      });
    });

    // Dismiss resolution warning
    document.querySelectorAll('.oc-resolution-warning').forEach(warnEl => {
      warnEl.addEventListener('click', e => {
        if (e.target === warnEl && warnEl.classList.contains('oc-res-warning')) {
          warnEl.style.display = 'none';
        }
      });
    });
  }
  setupSpotifyModal() {
    const dialog = document.getElementById('oc-spotify-share-dialog');
    if (!dialog) return;
    document.querySelectorAll('.oc-spotify-modal-trigger').forEach(trigger => {
      trigger.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        this.openSpotifyModal();
      });
    });
    dialog.querySelectorAll('[data-oc-spotify-modal-close]').forEach(closeBtn => {
      closeBtn.addEventListener('click', () => this.closeSpotifyModal());
    });
    dialog.addEventListener('click', event => {
      const rect = dialog.getBoundingClientRect();
      const inDialog = rect.top <= event.clientY && event.clientY <= rect.top + rect.height && rect.left <= event.clientX && event.clientX <= rect.left + rect.width;
      if (!inDialog) this.closeSpotifyModal();
    });
    dialog.addEventListener('close', () => {
      dialog.classList.remove('is-visible');
      document.body.style.overflow = '';
    });
  }
  openSpotifyModal() {
    const dialog = document.getElementById('oc-spotify-share-dialog');
    if (!dialog || dialog.open) return;
    clearTimeout(this.spotifyModalCloseTimer);
    dialog.showModal();
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        dialog.classList.add('is-visible');
      });
    });
    document.body.style.overflow = 'hidden';
  }
  closeSpotifyModal() {
    const dialog = document.getElementById('oc-spotify-share-dialog');
    if (!dialog || !dialog.open) return;
    dialog.classList.remove('is-visible');
    clearTimeout(this.spotifyModalCloseTimer);
    this.spotifyModalCloseTimer = setTimeout(() => {
      if (dialog.open) dialog.close();
      document.body.style.overflow = '';
    }, 300);
  }
  filterClipart(layerId) {
    const grid = document.querySelector(`.oc-clipart-grid[data-oc-clipart-grid="${layerId}"]`) || document.querySelector(`[data-oc-clipart-search="${layerId}"]`)?.closest('.oc-layer-body')?.querySelector('.oc-clipart-grid');
    if (!grid) return;
    const items = grid.querySelectorAll('.oc-clipart-item');
    const term = (this.clipartSearchTerms[layerId] || '').toLowerCase().trim();
    const category = this.clipartCategoryFilters[layerId] || '';
    let visibleCount = 0;
    items.forEach(btn => {
      const name = (btn.title || '').toLowerCase();
      const groups = btn.dataset.ocClipartGroups ? btn.dataset.ocClipartGroups.split('||').filter(Boolean) : [];
      const matchesSearch = !term || name.includes(term);
      const matchesCategory = !category || groups.includes(category);
      const visible = matchesSearch && matchesCategory;
      btn.style.display = visible ? '' : 'none';
      if (visible) visibleCount++;
    });
    let noResults = grid.querySelector('.oc-clipart-no-results');
    if (visibleCount === 0) {
      if (!noResults) {
        noResults = document.createElement('p');
        noResults.className = 'oc-clipart-no-results';
        noResults.textContent = 'No clipart matches your search.';
        grid.appendChild(noResults);
      }
      noResults.style.display = '';
    } else if (noResults) {
      noResults.style.display = 'none';
    }
  }
  setSpotifyError(layerId, message, inputEl = null) {
    const msg = String(message || '');
    const el = document.querySelector(`[data-oc-spotify-error="${layerId}"]`);
    if (el) {
      el.textContent = msg;
      el.style.display = msg ? '' : 'none';
    }
    if (inputEl) {
      inputEl.setCustomValidity(msg);
      inputEl.setAttribute('aria-invalid', msg ? 'true' : 'false');
    }
  }
  getLayerInputEl(layer) {
    if (!layer?.id) return null;
    switch (layer.type) {
      case 'text':
      case 'textarea':
        return document.querySelector(`[data-oc-layer-text="${layer.id}"]`);
      case 'spotify':
        return document.querySelector(`[data-oc-layer-spotify="${layer.id}"]`);
      case 'image':
        return document.querySelector(`[data-oc-upload-zone="${layer.id}"]`);
      case 'clipart':
        return document.querySelector(`[data-oc-layer-clipart="${layer.id}"]`);
      default:
        return null;
    }
  }
  clearPreflightMessages() {
    if (this.preflightRoot) {
      this.preflightRoot.innerHTML = '';
      this.preflightRoot.hidden = true;
    }
    document.querySelectorAll('.oc-preflight-field-error').forEach(el => {
      el.classList.remove('oc-preflight-field-error');
    });
    document.querySelectorAll('[data-oc-layer-text], [data-oc-layer-spotify]').forEach(el => {
      el.setCustomValidity('');
      el.setAttribute('aria-invalid', 'false');
    });
  }
  renderPreflightMessages(errors = [], warnings = []) {
    if (!this.preflightRoot) return;
    if (!errors.length && !warnings.length) {
      this.clearPreflightMessages();
      return;
    }
    const asList = (items, cls) => {
      return items.length ? `<ul class="${cls}">${items.map(msg => `<li>${msg}</li>`).join('')}</ul>` : '';
    };
    this.preflightRoot.innerHTML = '<div class="oc-preflight-box" role="alert" aria-live="assertive">' + (errors.length ? '<p class="oc-preflight-title">Please fix these issues before checkout:</p>' : '') + asList(errors, 'oc-preflight-errors') + (warnings.length ? '<p class="oc-preflight-title">Quality warnings:</p>' : '') + asList(warnings, 'oc-preflight-warnings') + '</div>';
    this.preflightRoot.hidden = false;
    this.preflightRoot.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }
  async getImageMeta(url) {
    if (!url) return null;
    return new Promise(resolve => {
      const img = new Image();
      img.onload = () => resolve({
        width: img.naturalWidth || 0,
        height: img.naturalHeight || 0
      });
      img.onerror = () => resolve(null);
      img.src = url;
    });
  }
  async runPreflight() {
    this.clearPreflightMessages();
    const errors = [];
    const warnings = [];
    const spotifyValidated = new Set();
    const invalidSpotifyStatuses = ['invalid_format', 'playlist_private_or_invalid', 'invalid_or_unavailable', 'unreachable', 'rate_limited'];
    for (const area of this.areas) {
      for (const layer of area.layers || []) {
        if (layer.locked) continue; // Locked layers skip preflight validation
        const input = this.inputs[layer.id] || {};
        const settings = layer.settings || {};
        const required = Boolean(settings.required);
        const label = `${area.label || 'Area'}: ${layer.label || layer.type}`;
        const fieldEl = this.getLayerInputEl(layer);
        let value = '';
        switch (layer.type) {
          case 'text':
          case 'textarea':
            value = String(input.value || '').trim();
            if (required && !value) {
              errors.push(`${label} is required.`);
              fieldEl?.classList.add('oc-preflight-field-error');
              if (fieldEl) {
                fieldEl.setCustomValidity('This field is required.');
                fieldEl.setAttribute('aria-invalid', 'true');
              }
            }
            if (value) {
              const charLimit = parseInt(settings.char_limit, 10) || 0;
              if (charLimit > 0 && value.length > charLimit) {
                errors.push(`${label} exceeds the ${charLimit} character limit.`);
                fieldEl?.classList.add('oc-preflight-field-error');
                if (fieldEl) {
                  fieldEl.setCustomValidity(`Maximum ${charLimit} characters.`);
                  fieldEl.setAttribute('aria-invalid', 'true');
                }
              }
            }
            break;
          case 'image':
            if (required && !input.attachmentId) {
              errors.push(`${label} needs an uploaded image.`);
              fieldEl?.classList.add('oc-preflight-field-error');
            }
            if (input.attachmentUrl) {
              let imageMeta = input.imageMeta || null;
              if (!imageMeta) {
                imageMeta = await this.getImageMeta(input.attachmentUrl);
                if (imageMeta && this.inputs[layer.id]) {
                  this.inputs[layer.id].imageMeta = imageMeta;
                }
              }
              if (imageMeta && imageMeta.width > 0 && imageMeta.height > 0) {
                if (imageMeta.width < layer.w || imageMeta.height < layer.h) {
                  warnings.push(`${label} may print soft (${imageMeta.width}x${imageMeta.height}px for a ${layer.w}x${layer.h}px print area).`);
                }
              }
            }
            break;
          case 'clipart':
            if (required && !input.clipartId) {
              errors.push(`${label} requires a clipart selection.`);
              fieldEl?.classList.add('oc-preflight-field-error');
            }
            break;
          case 'lineart':
            value = String(input.colorHex || '').trim();
            if (required && !value) {
              errors.push(`${label} requires a line-art colour.`);
              fieldEl?.classList.add('oc-preflight-field-error');
              if (fieldEl) {
                fieldEl.setCustomValidity('Please choose a line-art colour.');
                fieldEl.setAttribute('aria-invalid', 'true');
              }
            }
            break;
          case 'spotify':
            value = String(input.value || '').trim();
            if (required && !value) {
              errors.push(`${label} requires a Spotify link.`);
              fieldEl?.classList.add('oc-preflight-field-error');
              if (fieldEl) {
                fieldEl.setCustomValidity('Please provide a Spotify link.');
                fieldEl.setAttribute('aria-invalid', 'true');
              }
              break;
            }
            if (value && !spotifyValidated.has(layer.id)) {
              await this.validateSpotifyLayer(layer.id, value, fieldEl);
              spotifyValidated.add(layer.id);
            }
            if (value) {
              const status = String(this.inputs[layer.id]?.spotifyStatus || '');
              if (invalidSpotifyStatuses.includes(status)) {
                errors.push(`${label} has an invalid or unavailable Spotify link.`);
                fieldEl?.classList.add('oc-preflight-field-error');
                if (fieldEl) {
                  fieldEl.setCustomValidity('Spotify link is invalid or unavailable.');
                  fieldEl.setAttribute('aria-invalid', 'true');
                }
              }
            }
            break;
        }
      }
    }
    return {
      errors,
      warnings,
      ok: errors.length === 0
    };
  }
  async validateSpotifyLayer(layerId, rawValue, inputEl = null) {
    const value = String(rawValue || '').trim();
    if (!this.inputs[layerId]) this.inputs[layerId] = {};
    const token = (this.spotifyValidateTokens[layerId] || 0) + 1;
    this.spotifyValidateTokens[layerId] = token;
    if (!value) {
      this.inputs[layerId].spotifyStatus = '';
      this.inputs[layerId].spotifyUri = '';
      this.setSpotifyError(layerId, '', inputEl);
      this.scheduleRedraw();
      this.updateHiddenField();
      return;
    }
    const localUri = this.extractSpotifyUri(value);
    if (!localUri) {
      this.inputs[layerId].spotifyStatus = 'invalid_format';
      this.inputs[layerId].spotifyUri = '';
      this.setSpotifyError(layerId, 'Invalid Spotify link format.', inputEl);
      this.scheduleRedraw();
      this.updateHiddenField();
      return;
    }
    if (!this.data.validateSpotifyUrl) {
      this.inputs[layerId].spotifyStatus = 'ok';
      this.inputs[layerId].spotifyUri = localUri;
      this.setSpotifyError(layerId, '', inputEl);
      this.scheduleRedraw();
      this.updateHiddenField();
      return;
    }
    try {
      const res = await fetch(this.data.validateSpotifyUrl, {
        method: 'POST',
        headers: this.restHeaders({
          'Content-Type': 'application/json'
        }),
        body: JSON.stringify({
          url: value
        })
      });
      const isJson = res.headers.get('content-type')?.includes('application/json');
      let json = null;
      let text = '';
      if (isJson) {
        try {
          json = await res.json();
        } catch (err) {
          console.warn('[OC] Spotify validation JSON parse failed:', err);
        }
      } else {
        text = await res.text();
      }
      if (this.spotifyValidateTokens[layerId] !== token) return;
      if (!res.ok) {
        const statusReason = json?.code === 'rate_limited' || res.status === 429 ? 'rate_limited' : 'unreachable';
        const statusMessage = json?.message || text || 'Could not validate Spotify right now. Please try again.';
        this.inputs[layerId].spotifyStatus = statusReason;
        this.inputs[layerId].spotifyUri = '';
        this.setSpotifyError(layerId, statusMessage, inputEl);
        this.scheduleRedraw();
        this.updateHiddenField();
        return;
      }
      if (!json) {
        this.inputs[layerId].spotifyStatus = 'unreachable';
        this.inputs[layerId].spotifyUri = '';
        this.setSpotifyError(layerId, 'Could not validate Spotify right now. Please try again.', inputEl);
        this.scheduleRedraw();
        this.updateHiddenField();
        return;
      }
      const valid = Boolean(json?.valid);
      if (valid) {
        this.inputs[layerId].spotifyStatus = 'ok';
        this.inputs[layerId].spotifyUri = json.spotifyUri || localUri;
        this.setSpotifyError(layerId, '', inputEl);
      } else {
        this.inputs[layerId].spotifyStatus = json?.reason || 'invalid_or_unavailable';
        this.inputs[layerId].spotifyUri = '';
        this.setSpotifyError(layerId, json?.message || 'Spotify link is invalid or unavailable.', inputEl);
      }
    } catch (e) {
      if (this.spotifyValidateTokens[layerId] !== token) return;
      this.inputs[layerId].spotifyStatus = 'unreachable';
      this.inputs[layerId].spotifyUri = '';
      this.setSpotifyError(layerId, 'Could not validate Spotify right now. Please try again.', inputEl);
    }
    this.scheduleRedraw();
    this.updateHiddenField();
  }

  // ── Form submit — upload preview then proceed ──────────────────────────────

  updateInputsFromDOM() {
    for (const layerIdStr in this.inputs) {
      const layerId = parseInt(layerIdStr, 10);
      const inp = this.inputs[layerId];
      if (!inp) continue;
      const textEl = document.querySelector(`[data-oc-layer-text="${layerId}"]`);
      if (textEl && inp.value !== undefined) {
        textEl.value = inp.value;
      }
      const fontEl = document.querySelector(`[data-oc-layer-font="${layerId}"]`);
      if (fontEl && inp.fontId) {
        fontEl.value = inp.fontId;
      }
      const swatch = document.querySelector(`[data-oc-layer-swatch="${layerId}"][data-hex="${inp.colorHex}"]`);
      if (swatch) {
        swatch.closest('.oc-colour-swatches')?.querySelectorAll('.oc-colour-swatch').forEach(s => s.classList.toggle('oc-selected', s === swatch));
      }
      const colorEl = document.querySelector(`[data-oc-layer-color="${layerId}"]`);
      if (colorEl && inp.colorHex) {
        colorEl.value = inp.colorHex;
      }
      const sizeEl = document.querySelector(`[data-oc-layer-font-size="${layerId}"]`);
      if (sizeEl && inp.fontSize) {
        sizeEl.value = inp.fontSize;
      }
      const clipartBtn = document.querySelector(`[data-oc-layer-clipart="${layerId}"][data-oc-clipart="${inp.clipartId}"]`);
      if (clipartBtn) {
        clipartBtn.closest('.oc-clipart-grid')?.querySelectorAll('.oc-clipart-item').forEach(i => i.classList.toggle('oc-selected', i === clipartBtn));
      }
    }
    this.updateHiddenField();
    this.areas.forEach((_, i) => this.redraw(i));
  }
  setupFormSubmit() {
    const form = document.querySelector('form.cart');
    if (!form) return;
    if (this.editMode) {
      form.addEventListener('submit', async e => {
        e.preventDefault();
        const preflight = await this.runPreflight();
        this.renderPreflightMessages(preflight.errors, preflight.warnings);
        if (!preflight.ok) return;
        if (preflight.warnings.length) {
          const proceed = window.confirm('We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.');
          if (!proceed) return;
        }
        await this.uploadPreview();
        this.updateHiddenField();
        const layers = {};
        this.areas.forEach(area => {
          (area.layers || []).forEach(layer => {
            const inp = this.inputs[layer.id];
            if (inp) layers[layer.id] = {
              type: layer.type,
              ...inp
            };
          });
        });
        try {
          const res = await fetch(this.data.updateCartItemUrl, {
            method: 'POST',
            headers: this.restHeaders({
              'Content-Type': 'application/json'
            }),
            body: JSON.stringify({
              cart_key: this.cartKey,
              designId: this.data.designId,
              layers,
              previewUrl: this._previewUrl || ''
            })
          });
          let json = null;
          const isJson = res.headers.get('content-type')?.includes('application/json');
          if (isJson) {
            try {
              json = await res.json();
            } catch (err) {
              console.warn('[OC] Cart update response parse failed:', err);
            }
          }
          if (!res.ok) {
            this.renderPreflightMessages([json?.message || 'Failed to update customisation.'], []);
            return;
          }
          if (json?.success) {
            window.location.href = wc_cart_params?.cart_url || '/cart/';
          } else {
            this.renderPreflightMessages([json?.message || 'Failed to update customisation.'], []);
          }
        } catch (err) {
          console.error('[OC] Update cart item failed:', err);
          this.renderPreflightMessages(['Failed to update customisation. Please try again.'], []);
        }
      });
      return;
    }
    form.addEventListener('submit', async e => {
      if (form._ocSubmitReady) return; // preview already saved — let submit through

      const preflight = await this.runPreflight();
      this.renderPreflightMessages(preflight.errors, preflight.warnings);
      if (!preflight.ok) {
        e.preventDefault();
        return;
      }
      if (preflight.warnings.length) {
        const proceed = window.confirm('We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.');
        if (!proceed) {
          e.preventDefault();
          return;
        }
      }
      e.preventDefault();
      await this.uploadPreview();
      form._ocSubmitReady = true;
      // requestSubmit() re-triggers HTML5 validation before submitting.
      if (form.requestSubmit) {
        const submitter = form.querySelector('[type="submit"]') || undefined;
        form.requestSubmit(submitter);
      } else {
        form.submit();
      }
    });
  }
  async uploadPreview() {
    const canvas = this.canvases[this.activeArea];
    if (!canvas || !this.data.savePreviewUrl) return;
    let dataUrl;
    try {
      dataUrl = canvas.toDataURL({
        format: 'jpeg',
        quality: 0.85
      });
    } catch (e) {
      this._previewUrl = '';
      this.updateHiddenField();
      console.warn('[OC] Could not capture preview for cart:', e.message);
      return;
    }
    try {
      const res = await fetch(this.data.savePreviewUrl, {
        method: 'POST',
        headers: this.restHeaders({
          'Content-Type': 'application/json'
        }),
        body: JSON.stringify({
          image: dataUrl
        })
      });
      if (!res.ok) {
        this._previewUrl = '';
        this.updateHiddenField();
        return;
      }
      let json = null;
      const isJson = res.headers.get('content-type')?.includes('application/json');
      if (isJson) {
        try {
          json = await res.json();
        } catch (err) {
          console.warn('[OC] Preview upload JSON parse failed:', err);
        }
      }
      if (!json) {
        this._previewUrl = '';
        this.updateHiddenField();
        return;
      }
      if (json.url) {
        this._previewUrl = json.url;
        this.updateHiddenField(); // embed previewUrl in the cart payload
      }
    } catch (e) {
      this._previewUrl = '';
      this.updateHiddenField();
      // Non-fatal — cart submits without a preview image.
      console.warn('[OC] Preview upload failed:', e.message);
    }
  }
  switchArea(index) {
    this.activeArea = index;
    document.querySelectorAll('.oc-area-tab').forEach((btn, i) => {
      btn.classList.toggle('oc-active', i === index);
      btn.setAttribute('aria-selected', i === index ? 'true' : 'false');
      btn.setAttribute('tabindex', i === index ? '0' : '-1');
    });
    document.querySelectorAll('.oc-area-controls').forEach(el => {
      el.style.display = parseInt(el.dataset.areaIndex, 10) === index ? '' : 'none';
    });
    this.redraw(index);
    if (window.innerWidth < 640) {
      const activeTab = document.querySelector(`.oc-area-tab[aria-selected="true"]`);
      if (activeTab) {
        activeTab.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest',
          inline: 'center'
        });
      }
    }
  }
  setupMobilePreview() {
    const toggleBtn = document.getElementById('oc-preview-toggle');
    const canvasWrap = document.getElementById('oc-canvas-wrap');
    if (!toggleBtn || !canvasWrap) return;
    const updateToggle = () => {
      const isVisible = canvasWrap.classList.contains('oc-preview-visible');
      this._mobilePreviewVisible = isVisible;
      toggleBtn.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
      toggleBtn.textContent = isVisible ? 'Hide Preview' : 'Show Preview';
    };
    toggleBtn.addEventListener('click', () => {
      canvasWrap.classList.toggle('oc-preview-visible');
      updateToggle();
    });
    toggleBtn.addEventListener('touchend', e => {
      e.preventDefault();
      toggleBtn.click();
    }, {
      passive: false
    });
  }

  // ── Uppy upload zones ────────────────────────────────────────────────────────

  setupUploadZones() {
    document.querySelectorAll('[data-oc-upload-zone]').forEach(zoneEl => {
      const lid = parseInt(zoneEl.dataset.ocUploadZone, 10);
      if (!lid) return;
      const uploadUrl = this.data?.uploadUrl || '';
      if (!uploadUrl) {
        this.showUploadError(zoneEl, 'Uploads are unavailable right now.');
        return;
      }

      // Find the layer's per-layer settings; fall back to global defaults.
      let layer = null;
      for (const area of this.areas) {
        layer = (area.layers || []).find(l => l.id === lid);
        if (layer) break;
      }
      if (!layer) {
        console.warn('[OC] Upload zone has no matching layer:', lid);
        return;
      }
      const layerFormats = Array.isArray(layer?.settings?.formats) ? layer.settings.formats : [];
      const globalFormats = Array.isArray(this.data.allowedFormats) ? this.data.allowedFormats : [];
      const serverFormats = ['jpg', 'jpeg', 'png', 'svg', 'pdf', 'eps', 'webp'];
      const effective = (layerFormats.length ? layerFormats : globalFormats).map(f => String(f).toLowerCase().replace(/^\./, '')).filter(ext => serverFormats.includes(ext));
      const allowedExt = effective.length ? effective.map(ext => `.${ext}`) : ['.jpg', '.jpeg', '.png', '.svg', '.pdf', '.webp'];
      const layerMaxMb = parseInt(layer?.settings?.max_size_mb, 10);
      const globalMaxMb = parseInt(this.data.maxUploadSizeMb, 10);
      const maxMb = layerMaxMb > 0 ? layerMaxMb : globalMaxMb > 0 ? globalMaxMb : 10;
      const uppy = new _uppy_core__WEBPACK_IMPORTED_MODULE_1__["default"]({
        autoProceed: true,
        onBeforeFileAdded: () => {
          uppy.getFiles().forEach(existingFile => uppy.removeFile(existingFile.id));
          this.setUploadZoneState(zoneEl, '');
          const warnEl = document.querySelector(`.oc-resolution-warning[data-oc-resolution-warning="${lid}"]`);
          if (warnEl) warnEl.style.display = 'none';
          this.setUploadProgress(zoneEl, 0, 'Starting upload...');
          this.showUploadError(zoneEl, '');
          return true;
        },
        restrictions: {
          maxNumberOfFiles: 1,
          maxFileSize: maxMb * 1024 * 1024,
          allowedFileTypes: allowedExt
        }
      });
      uppy.use(_uppy_drag_drop__WEBPACK_IMPORTED_MODULE_2__["default"], {
        target: zoneEl,
        note: 'We accept ' + (allowedExt.length ? allowedExt.map(e => e.replace('.', '').toUpperCase()).join(', ') : 'JPG, PNG, PDF, EPS') + ' and other common image types.',
        locale: {
          strings: {
            dropHereOr: '%{browse}',
            browse: 'Tap / click here to upload your image'
          }
        }
      });
      uppy.use(_uppy_xhr_upload__WEBPACK_IMPORTED_MODULE_3__["default"], {
        endpoint: this.uploadEndpoint(uploadUrl, lid),
        formData: true,
        fieldName: 'artwork'
      });
      uppy.on('upload-progress', (file, progress) => {
        const percent = progress?.bytesTotal ? Math.round(progress.bytesUploaded / progress.bytesTotal * 100) : 0;
        this.setUploadProgress(zoneEl, percent, `Uploading ${percent}%`);
      });
      uppy.on('upload-success', async (file, res) => {
        console.log('[OC] Upload success — response body:', res?.body);
        this.setUploadProgress(zoneEl, 100, '');
        if (!res?.body) {
          this.setUploadZoneState(zoneEl, 'error');
          this.showUploadError(zoneEl, 'Upload succeeded but server returned no data.');
          return;
        }
        if (!this.inputs[lid]) this.inputs[lid] = {};
        this.inputs[lid].attachmentId = res.body.attachment_id || 0;
        this.inputs[lid].attachmentUrl = res.body.preview_url || '';
        this.inputs[lid].imageMeta = null;
        if (!this.inputs[lid].attachmentUrl) {
          this.setUploadZoneState(zoneEl, 'error');
          this.showUploadError(zoneEl, 'Server did not return a preview URL.');
          return;
        }
        const actions = zoneEl.closest('.oc-artwork-wrap')?.querySelector('.oc-artwork-actions');
        if (actions) actions.style.display = '';
        const meta = await this.getImageMeta(this.inputs[lid].attachmentUrl);
        if (meta && this.inputs[lid]) {
          this.inputs[lid].imageMeta = meta;
          const thresholdW = Math.round(layer.w * (300 / 72));
          const thresholdH = Math.round(layer.h * (300 / 72));
          const warnEl = document.querySelector(`.oc-resolution-warning[data-oc-resolution-warning="${lid}"]`);
          if (warnEl) {
            const belowThreshold = meta.width < thresholdW || meta.height < thresholdH;
            const belowHalf = meta.width < thresholdW * 0.5 || meta.height < thresholdH * 0.5;
            if (belowHalf) {
              warnEl.className = 'oc-resolution-warning oc-res-error';
              warnEl.textContent = `This image is too low resolution for quality printing. Minimum required: ${thresholdW} x ${thresholdH} pixels.`;
              warnEl.style.display = '';
              this.inputs[lid].attachmentId = 0;
              this.inputs[lid].attachmentUrl = '';
              this.inputs[lid].imageMeta = null;
              if (actions) actions.style.display = 'none';
              this.setUploadZoneState(zoneEl, 'error');
              this.showUploadError(zoneEl, 'Image resolution too low. Please upload a higher resolution image.');
              this.scheduleRedraw();
              this.updateHiddenField();
              return;
            } else if (belowThreshold) {
              warnEl.className = 'oc-resolution-warning oc-res-warning';
              warnEl.textContent = `This image may not print clearly at full size. Recommended minimum: ${thresholdW} x ${thresholdH} pixels.`;
              warnEl.style.display = '';
            } else {
              warnEl.style.display = 'none';
            }
          }
        }
        this.setUploadZoneState(zoneEl, 'uploaded');
        this.requestPreviewFocus();
        this.scheduleRedraw();
        this.updateHiddenField();
        this.showUploadError(zoneEl, '');
      });
      uppy.on('upload-error', (file, error, response) => {
        let responseBody = response?.body || null;
        if (!responseBody && response?.responseText) {
          try {
            responseBody = JSON.parse(response.responseText);
          } catch (e) {
            responseBody = {
              message: response.responseText
            };
          }
        }
        const msg = responseBody?.message || error?.message || 'Upload failed.';
        console.warn('[OC] Upload error:', msg, response);
        this.setUploadZoneState(zoneEl, 'error');
        this.setUploadProgress(zoneEl, 0, '');
        this.showUploadError(zoneEl, msg);
      });
      uppy.on('restriction-failed', (file, error) => {
        this.setUploadZoneState(zoneEl, 'error');
        this.setUploadProgress(zoneEl, 0, '');
        this.showUploadError(zoneEl, error?.message || 'File not allowed.');
      });
    });
  }
  clearUploadedImage(layerId, zoneEl) {
    if (this.inputs[layerId]) {
      this.inputs[layerId].attachmentId = 0;
      this.inputs[layerId].attachmentUrl = '';
      this.inputs[layerId].imageMeta = null;
    }
    const actions = zoneEl.closest('.oc-artwork-wrap')?.querySelector('.oc-artwork-actions');
    if (actions) actions.style.display = 'none';
    const warnEl = document.querySelector(`.oc-resolution-warning[data-oc-resolution-warning="${layerId}"]`);
    if (warnEl) warnEl.style.display = 'none';
    this.setUploadZoneState(zoneEl, '');
    this.requestPreviewFocus();
    this.scheduleRedraw();
    this.updateHiddenField();
    this.showUploadError(zoneEl, '');
  }
  setUploadZoneState(zoneEl, state) {
    zoneEl.classList.toggle('oc-upload-zone--uploaded', state === 'uploaded');
    zoneEl.classList.toggle('oc-upload-zone--error', state === 'error');
    const browse = zoneEl.querySelector('.uppy-DragDrop-browse');
    const note = zoneEl.querySelector('.uppy-DragDrop-note');
    if (browse) {
      browse.textContent = state === 'uploaded' ? 'Image uploaded' : 'Tap / click here to upload your image';
    }
    if (note) {
      if (!note.dataset.ocOriginalText) note.dataset.ocOriginalText = note.textContent;
      note.textContent = state === 'uploaded' ? 'Click to replace image' : note.dataset.ocOriginalText || note.textContent;
    }
  }
  setUploadProgress(zoneEl, percent, label) {
    const wrap = zoneEl.closest('.oc-artwork-wrap');
    if (!wrap) return;
    let progressEl = wrap.querySelector('.oc-upload-progress');
    if (!progressEl) {
      progressEl = document.createElement('div');
      progressEl.className = 'oc-upload-progress';
      progressEl.innerHTML = '<div class="oc-upload-progress-label"></div><div class="oc-upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="oc-upload-progress-bar"></div></div>';
      zoneEl.insertAdjacentElement('afterend', progressEl);
    }
    const safePercent = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
    const labelEl = progressEl.querySelector('.oc-upload-progress-label');
    const track = progressEl.querySelector('.oc-upload-progress-track');
    const bar = progressEl.querySelector('.oc-upload-progress-bar');
    if (labelEl) labelEl.textContent = label || '';
    if (track) track.setAttribute('aria-valuenow', String(safePercent));
    if (bar) bar.style.width = `${safePercent}%`;
    progressEl.style.display = label ? '' : 'none';
  }
  showUploadError(zoneEl, message) {
    const wrap = zoneEl.closest('.oc-artwork-wrap');
    if (!wrap) return;
    let err = wrap.querySelector('.oc-artwork-error');
    if (!err) {
      err = document.createElement('div');
      err.className = 'oc-artwork-error';
      err.style.cssText = 'color:#b32d2e;font-size:12px;margin-top:6px;';
      wrap.appendChild(err);
    }
    err.textContent = message || '';
    err.style.display = message ? '' : 'none';
  }

  // ── Cart serialisation ────────────────────────────────────────────────────────

  updateHiddenField() {
    const el = document.getElementById('oc-customisation-data');
    if (!el) return;
    const layers = {};
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        const inp = this.inputs[layer.id];
        if (inp) layers[layer.id] = {
          type: layer.type,
          ...inp
        };
      });
    });
    const payload = {
      v: 2,
      designId: this.data.designId,
      layers
    };
    if (this._previewUrl) payload.previewUrl = this._previewUrl;
    el.value = JSON.stringify(payload);
  }
}
})();

/******/ })()
;
//# sourceMappingURL=customiser-app.js.map