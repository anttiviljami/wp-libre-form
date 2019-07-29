!function(e,t){"object"==typeof exports&&"object"==typeof module?module.exports=t():"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?exports.WPLF=t():e.WPLF=t()}(this,function(){return function(e){var t={};function r(n){if(t[n])return t[n].exports;var o=t[n]={i:n,l:!1,exports:{}};return e[n].call(o.exports,o,o.exports,r),o.l=!0,o.exports}return r.m=e,r.c=t,r.d=function(e,t,n){r.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:n})},r.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},r.t=function(e,t){if(1&t&&(e=r(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var n=Object.create(null);if(r.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)r.d(n,o,function(t){return e[t]}.bind(null,o));return n},r.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return r.d(t,"a",t),t},r.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},r.p="",r(r.s=4)}([function(e,t,r){"use strict";function n(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}t.a=function(e){return function(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{},o=Object.keys(r);"function"==typeof Object.getOwnPropertySymbols&&(o=o.concat(Object.getOwnPropertySymbols(r).filter(function(e){return Object.getOwnPropertyDescriptor(r,e).enumerable}))),o.forEach(function(t){n(e,t,r[t])})}return e}({},e.WPLF_DATA)}(window)},,,,function(e,t,r){e.exports=r(5)},function(e,t,r){"use strict";r.r(t);var n=r(0);function o(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}var a=function(){function e(t){if(function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),t instanceof HTMLFormElement!=!0)throw new Error("Form element invalid or missing");this.form=t,this.submitState=null,this.submitHandler=null,this.callbacks={beforeSend:{},success:{},error:{}},this.key="_"+Math.random().toString(36).substr(2,9),this.addSubmitHandler()}var t,r,a;return t=e,(r=[{key:"addCallback",value:function(e,t,r){return this.callbacks[t][e]=r,this}},{key:"removeCallback",value:function(e,t){return delete this.callbacks[t][e],this}},{key:"runCallback",value:function(e){for(var t=this,r=arguments.length,n=new Array(r>1?r-1:0),o=1;o<r;o++)n[o-1]=arguments[o];var a=window.wplf;if((a.successCallbacks.length||a.errorCallbacks.length||a.beforeSendCallbacks.length)&&(console.warn('WP Libre Form 2.0 introduced breaking changes to window.wplf "API", please migrate to the new API ASAP.'),a.beforeSendCallbacks.forEach(function(e){e.apply(void 0,n)}),a.errorCallbacks.forEach(function(e){e.apply(void 0,n)}),a.successCallbacks.forEach(function(e){e.apply(void 0,n)})),!this.callbacks[e])throw new Error("Unknown callback ".concat(name," ").concat(e));Object.keys(this.callbacks[e]).forEach(function(r){var o;(o=t.callbacks[e])[r].apply(o,n)})}},{key:"addSubmitHandler",value:function(e){var t=this;return this.submitHandler=e||function(e){e.preventDefault(),"sending"!==t.form.submitState&&(t.form.classList.add("sending"),[].forEach.call(t.form.querySelectorAll(".wplf-error"),function(e){e.parentNode.removeChild(e)}),t.send().then(function(e){return e.text()}).then(function(e){var r=JSON.parse(e);if("success"in r){var n=document.createElement("p");n.className="wplf-success",n.innerHTML=r.success,t.form.parentNode.insertBefore(n,t.form.nextSibling)}if("ok"in r&&r.ok&&(t.form.parentNode.removeChild(t.form),t.submitStatus="success",t.runCallback("success",r,t)),"error"in r){var o=document.createElement("p");o.className="wplf-error error",o.textContent=r.error,t.form.appendChild(o),t.submitStatus=new Error(r.error),t.runCallback("error",t.submitStatus,t)}t.form.classList.remove("sending")}).catch(function(e){t.form.classList.remove("sending"),t.runCallback("error",e,t),console.warn("Fetch error: ",e)}))},this.form.addEventListener("submit",this.submitHandler),this}},{key:"removeSubmitHandler",value:function(){return this.form.removeEventListener("submit",this.submitHandler),this.submitHandler=null,this}},{key:"send",value:function(){var e=this.form,t=new FormData(e);return n.a.lang&&t.append("lang",n.a.lang),e.submitState="submitting",this.runCallback("beforeSend",e,this),fetch(n.a.ajax_url,{method:"POST",credentials:n.a.ajax_credentials||"same-origin",body:t,headers:n.a.request_headers||{}})}}])&&o(t.prototype,r),a&&o(t,a),e}();function i(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}function s(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}var l=[],u=0;function c(){return l.length===u}function f(e){var t=document.createElement("script");t.src=n.a.wplf_assets_dir+"/scripts/polyfills/"+e+".js",t.addEventListener("load",function(){u++,c()&&window.postMessage("[WPLF] Polyfills loaded","*")}),document.body.appendChild(t)}window.fetch||l.push("fetch"),window.Promise||l.push("promise");var d=function(){function e(){var t=this;!function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,e),s(this,"forms",{}),s(this,"Form",a),this.isReady()?this.initialize():(l.forEach(f),this.whenReady(function(){return t.initialize()}))}var t,r,o;return t=e,(r=[{key:"initialize",value:function(){var e=this,t={beforeSendCallbacks:[],errorCallbacks:[],successCallbacks:[],attach:function(t){return e.attach(t)},submitHandler:function(e){e.preventDefault(),alert("Form can't be submitted properly due to configuration error. WP Libre Form 2.0 doesn't support the legacy wplf.submitHandler.")}};window.wplf=t,n.a.settings.autoinit&&[].forEach.call(document.querySelectorAll(".libre-form"),function(t){return e.attach(t)})}},{key:"isReady",value:function(){return c()}},{key:"_listenForWPLFMessage",value:function(e){var t=this,r=arguments.length>1&&void 0!==arguments[1]?arguments[1]:function(){return null};window.addEventListener("message",function(n){"string"==typeof n.data&&0===n.data.indexOf("[WPLF] ".concat(e))&&r(t)})}},{key:"whenReady",value:function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:function(){return null};this._listenForWPLFMessage("Polyfills loaded",e)}},{key:"findFormsById",value:function(e){var t=this;return Object.keys(this.forms).reduce(function(r,n){var o=t.forms[n];return parseInt(o.form.getAttribute("data-form-id"),10)===parseInt(e,10)&&r.push(o),r},[])}},{key:"attach",value:function(e){if(e instanceof a){var t=e;return this.forms[t.key]=t,t}var r=e;if(r instanceof HTMLFormElement!=!0)throw new Error("Unable to attach WPLF to element",r);var n=new a(r);return this.forms[n.key]=n,n.form.removeAttribute("tabindex"),n.form.removeAttribute("style"),n}},{key:"detach",value:function(e){if(e instanceof a){var t=e;return this.forms[t.key].removeSubmitHandler(),delete this.forms[t.key],!0}var r=e;if(r instanceof HTMLFormElement!=!0)throw new Error("Unable to detach WPLF from element",r);return this.forms[r].removeSubmitHandler(),delete this.forms[r],!0}}])&&i(t.prototype,r),o&&i(t,o),e}();t.default=new d}]).default});