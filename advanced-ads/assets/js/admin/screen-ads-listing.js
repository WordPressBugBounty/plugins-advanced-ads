(()=>{"use strict";var e={n:n=>{var t=n&&n.__esModule?()=>n.default:()=>n;return e.d(t,{a:t}),t},d:(n,t)=>{for(var i in t)e.o(t,i)&&!e.o(n,i)&&Object.defineProperty(n,i,{enumerable:!0,get:t[i]})},o:(e,n)=>Object.prototype.hasOwnProperty.call(e,n)};const n=jQuery;var t=e.n(n);const i=wp.apiFetch;var o=e.n(i);function a(e){return a="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},a(e)}function d(e){o()({path:"/advanced-ads/v1/quick_edit_data",method:"POST",data:{id:e}}).then((function(n){!function(e,n){var i=t()("#edit-".concat(e));if(i.find(".advads-quick-edit").prop("disabled",!1),i.find('[name="debugmode"]').prop("checked",n.debug_mode),n.expiry.expires){i.find('[name="enable_expiry"]').prop("checked",!0);var o=i.find(".expiry-inputs").show();for(var a in n.expiry.expiry_date)o.find('[name="'.concat(a,'"]')).val(n.expiry.expiry_date[a])}var d=i.find('[name="ignore_privacy"]');d.length&&d.prop("checked",n.ignore_privacy);var c=i.find('[name="ad_label"]');c.length&&c.val(n.ad_label);wp.hooks.doAction("advanced-ads-quick-edit-fields-init",e,n)}(e,n)}))}var c=function(){t()(".search-box").toggle(),t()(".tablenav.top .alignleft.actions:not(.bulkactions)").toggle()};function r(){t()("#advads-show-filters").on("click",c),t()("#advads-reset-filters").length&&c()}function l(){t()("#advads-ad-filter-customize").on("click",(function(){t()("#show-settings-link").trigger("click")}))}t()((function(){var e;e=window.inlineEditPost.edit,window.inlineEditPost.edit=function(n){e.apply(this,arguments),"object"===a(n)&&d(parseInt(this.getId(n),10))},t()(document).on("change",'.advads-bulk-edit [name="expiry_date"]',(function(){var e=t()(this);e.closest("fieldset").find(".expiry-inputs").css("display","on"===e.val()?"block":"none")})),t()(document).on("click",'[name="enable_expiry"]',(function(){var e=t()(this);e.closest("fieldset").find(".expiry-inputs").css("display",e.prop("checked")?"block":"none")})),t()((function(){t()('.inline-edit-group select option[value="private"]').remove()})),r(),l()}))})();