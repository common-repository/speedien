<?php 

function speedien_check_cache_eligibility()
{
    $bypass_cache = 0;

    $speedien_cache_exclusions = array('wp-login.php','wp-cron.php','wp-admin','wc-api','wp-json','my-account','cart','checkout','amp','feed','page','tag','search','xmlrpc','.xml','disable');

    foreach($speedien_cache_exclusions as $slug)
    {
        if(strpos(strtolower($_SERVER['REQUEST_URI']), $slug) !== false)
        {
            $bypass_cache = 1;
        }
    }

    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        $bypass_cache = 1;
    }
 
    if ( is_404() ) {
        $bypass_cache = 1;
    }

    if ( is_user_logged_in() ) {
        $bypass_cache = 1;
    }

    if (!isset($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], ['HEAD', 'GET'])) {
        $bypass_cache = 1;
    }

    if(!empty($_GET['s']) || !empty($_GET['no-optimization'])) {
        $bypass_cache = 1;
    }

    if(!empty($_COOKIE)) 
    {
        if(!empty($_COOKIE['wordpress_logged_in_1']))
        {
            $bypass_cache = 1;
        }
        $cookielist = '/(wordpress_[a-f0-9]+|comment_author|wp-postpass|wordpress_no_cache|woocommerce_cart_hash|woocommerce_items_in_cart|woocommerce_recently_viewed|edd_items_in_cart)/';
        $cookies = sanitize_text_field(implode('', array_keys($_COOKIE)));
        if (preg_match($cookielist, $cookies)) {
            $bypass_cache = 1;
        }
    }

    $options = get_option('speedien_options');
    if(!empty($options['speedien_field_pagelist']))
    {
        $page_exclusions = explode(PHP_EOL, $options['speedien_field_pagelist']);
        foreach($page_exclusions as $exclusion)
        {
            if(strpos(strtolower($_SERVER['REQUEST_URI']), trim($exclusion)) !== false)
            {
                $bypass_cache = 1;
            }
        }
    }

    return $bypass_cache;
}

function speedien_process_page()
{
    $bypass_cache = speedien_check_cache_eligibility();
    if(!$bypass_cache)
    {
        ob_start("speedien_cdn_rewrite");
    }
}

function speedien_cdn_rewrite($buffer)
{
    $REPLACEMENTS = [];
        $searchOffset = 0;
        while (preg_match('/<script\b[^>]*?>/is', $buffer, $matches, PREG_OFFSET_CAPTURE, $searchOffset)) {
            $offset = $matches[0][1];
            $searchOffset = $offset + 1;
            if (preg_match('/<\/\s*script>/is', $buffer, $endMatches, PREG_OFFSET_CAPTURE, $matches[0][1])) {
                $len = $endMatches[0][1] - $matches[0][1] + strlen($endMatches[0][0]);
                $everything = substr($buffer, $matches[0][1], $len);
                $tag = $matches[0][0];
                $closingTag = $endMatches[0][0];

                $hasSrc = preg_match('/\s+src=/i', $tag);
                $hasType = preg_match('/\s+type=/i', $tag);
                $shouldReplace = !$hasType || preg_match('/\s+type=([\'"])((application|text)\/(javascript|ecmascript|html|template)|module)\1/i', $tag);
                $noOptimize = preg_match('/data-wpspdn-nooptimize="true"/i', $tag);
                if ($shouldReplace && !$hasSrc && !$noOptimize) {
                    // inline script
                    $content = substr($buffer, $matches[0][1] + strlen($matches[0][0]), $endMatches[0][1] - $matches[0][1] - strlen($matches[0][0]));
                    if (apply_filters('wpspdn_exclude', false, $content)) {
                        $replacement = preg_replace('/^<script\b/i', '<script data-wpspdn-nooptimize="true"', $everything);
                        $buffer = substr_replace($buffer, $replacement, $offset, $len);
                        continue;
                    }
                    $replacement = $tag . "wpspdn[" . count($REPLACEMENTS) . "]wpspdn" . $closingTag;
                    $REPLACEMENTS[] = $content;
                    $buffer = substr_replace($buffer, $replacement, $offset, $len);
                    continue;
                }
            }
        }

        $buffer = preg_replace_callback('/<script\b[^>]*?>/i', function ($matches) {
            list($tag) = $matches;

            $EXTRA = '';

            $result = $tag;
            if (!preg_match('/\s+data-src=/i', $result) 
                && !preg_match('/data-wpspdn-nooptimize="true"/i', $result)
                && !preg_match('/data-rocketlazyloadscript=/i', $result)) {

                $src = preg_match('/\s+src=([\'"])(.*?)\1/i', $result, $matches)
                    ? $matches[2]
                    : null;
                if (!$src) {
                    // trying to detect src without quotes
                    $src = preg_match('/\s+src=([\/\w\-\.\~\:\[\]\@\!\$\?\&\#\(\)\*\+\,\;\=\%]+)/i', $result, $matches)
                        ? $matches[1]
                        : null;
                }
                $hasType = preg_match('/\s+type=/i', $result);
                $isJavascript = !$hasType
                    || preg_match('/\s+type=([\'"])((application|text)\/(javascript|ecmascript)|module)\1/i', $result)
                    || preg_match('/\s+type=((application|text)\/(javascript|ecmascript)|module)/i', $result);
                if ($isJavascript) {
                    if ($src) {
                        if (apply_filters('wpspdn_exclude', false, $src)) {
                            return $result;
                        }
                        $result = preg_replace('/\s+src=/i', " data-src=", $result);
                        $result = preg_replace('/\s+(async|defer)\b/i', " data-\$1", $result);
                    }
                    if ($hasType) {
                        $result = preg_replace('/\s+type=([\'"])module\1/i', " type=\"javascript/blocked\" data-wpspdn-module ", $result);
                        $result = preg_replace('/\s+type=module\b/i', " type=\"javascript/blocked\" data-wpspdn-module ", $result);
                        $result = preg_replace('/\s+type=([\'"])(application|text)\/(javascript|ecmascript)\1/i', " type=\"javascript/blocked\"", $result);
                        $result = preg_replace('/\s+type=(application|text)\/(javascript|ecmascript)\b/i', " type=\"javascript/blocked\"", $result);
                    } else {
                        $result = preg_replace('/<script/i', "<script type=\"javascript/blocked\"", $result);
                    }
                    $result = preg_replace('/<script/i', "<script ${EXTRA} data-wpspdn-after=\"REORDER\"", $result);
                }
            }
            return preg_replace('/\s*data-wpspdn-nooptimize="true"/i', '', $result);
        }, $buffer);

        $buffer = preg_replace_callback('/wpspdn\[(\d+)\]wpspdn/', function ($matches) use (&$REPLACEMENTS) {
            return $REPLACEMENTS[(int)$matches[1]];
        }, $buffer);

        $buffer = str_replace('</head>','<script data-cfasync="false">var _wpspdn={"rdelay":86400000,"elementor-animations":true,"elementor-pp":true,"v":"2.3.10"};if(navigator.userAgent.match(/MSIE|Internet Explorer/i)||navigator.userAgent.match(/Trident\/7\..*?rv:11/i)){var href=document.location.href;if(!href.match(/[?&]wpspdndisable/)){if(href.indexOf("?")==-1){if(href.indexOf("#")==-1){document.location.href=href+"?wpspdndisable=1"}else{document.location.href=href.replace("#","?wpspdndisable=1#")}}else{if(href.indexOf("#")==-1){document.location.href=href+"&wpspdndisable=1"}else{document.location.href=href.replace("#","&wpspdndisable=1#")}}}}</script><script data-cfasync="false">!function(t){var e={};function n(r){if(e[r])return e[r].exports;var o=e[r]={i:r,l:!1,exports:{}};return t[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=t,n.c=e,n.d=function(t,e,r){n.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:r})},n.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},n.t=function(t,e){if(1&e&&(t=n(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var o in t)n.d(r,o,function(e){return t[e]}.bind(null,o));return r},n.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return n.d(e,"a",e),e},n.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},n.p="/",n(n.s=0)}([function(t,e,n){t.exports=n(1)},function(t,e,n){"use strict";n.r(e);var r=new(function(){function t(){this.l=[]}var e=t.prototype;return e.emit=function(t,e){void 0===e&&(e=null),this.l[t]&&this.l[t].forEach((function(t){return t(e)}))},e.on=function(t,e){var n;(n=this.l)[t]||(n[t]=[]),this.l[t].push(e)},e.off=function(t,e){this.l[t]=(this.l[t]||[]).filter((function(t){return t!==e}))},t}()),o=new Date,i=document,a=function(){function t(){this.known=[]}var e=t.prototype;return e.init=function(){var t,e=this,n=!1,o=function(t){if(!n&&t&&t.fn&&!t.__wpspdn){var r=function(e){return i.addEventListener("DOMContentLoaded",(function(n){e.bind(i)(t,n)})),this};e.known.push([t,t.fn.ready,t.fn.init.prototype.ready]),t.fn.ready=r,t.fn.init.prototype.ready=r,t.__wpspdn=!0}return t};window.jQuery&&(t=o(window.jQuery)),Object.defineProperty(window,"jQuery",{get:function(){return t},set:function(e){return t=o(e)}}),r.on("l",(function(){return n=!0}))},e.unmock=function(){this.known.forEach((function(t){var e=t[0],n=t[1],r=t[2];e.fn.ready=n,e.fn.init.prototype.ready=r}))},t}(),c={};!function(t,e){try{var n=Object.defineProperty({},e,{get:function(){c[e]=!0}});t.addEventListener(e,null,n),t.removeEventListener(e,null,n)}catch(t){}}(window,"passive");var u=c,f=window,d=document,s=["mouseover","keydown","touchmove","touchend","wheel"],l=["mouseover","mouseout","touchstart","touchmove","touchend","click"],p="data-wpspdn-",v=function(){function t(){}return t.prototype.init=function(t){var e=!1,n=!1,o=function t(o){e||(e=!0,s.forEach((function(e){return d.body.removeEventListener(e,t,u)})),clearTimeout(n),location.href.match(/wpspdnnopreload/)||r.emit("pre"),r.emit("fi"))},i=function(t){var e=new MouseEvent("click",{view:t.view,bubbles:!0,cancelable:!0});return Object.defineProperty(e,"target",{writable:!1,value:t.target}),e};t<1e4&&r.on("i",(function(){e||(n=setTimeout(o,t))}));var a=[],c=function(t){t.target&&"dispatchEvent"in t.target&&("click"===t.type?(t.preventDefault(),t.stopPropagation(),a.push(i(t))):"touchmove"!==t.type&&a.push(t),t.target.setAttribute(p+t.type,!0))};r.on("l",(function(){var t;for(l.forEach((function(t){return f.removeEventListener(t,c)}));t=a.shift();){var e=t.target;e.getAttribute(p+"touchstart")&&e.getAttribute(p+"touchend")&&!e.getAttribute(p+"click")?(e.getAttribute(p+"touchmove")||(e.removeAttribute(p+"touchmove"),a.push(i(t))),e.removeAttribute(p+"touchstart"),e.removeAttribute(p+"touchend")):e.removeAttribute(p+t.type),e.dispatchEvent(t)}}));d.addEventListener("DOMContentLoaded",(function t(){s.forEach((function(t){return d.body.addEventListener(t,o,u)})),l.forEach((function(t){return f.addEventListener(t,c)})),d.removeEventListener("DOMContentLoaded",t)}))},t}(),m=document,h=m.createElement("span");h.setAttribute("id","elementor-device-mode"),h.setAttribute("class","elementor-screen-only");var y=window,b=document,g=b.documentElement,w=function(t){return t.getAttribute("class")||""},E=function(t,e){return t.setAttribute("class",e)},L=function(){window.addEventListener("load",(function(){var t=(m.body.appendChild(h),getComputedStyle(h,":after").content.replace(/"/g,"")),e=Math.max(g.clientWidth||0,y.innerWidth||0),n=Math.max(g.clientHeight||0,y.innerHeight||0),o=["_animation_"+t,"animation_"+t,"_animation","_animation","animation"];Array.from(b.querySelectorAll(".elementor-invisible")).forEach((function(t){var i=t.getBoundingClientRect();if(i.top+y.scrollY<=n&&i.left+y.scrollX<e)try{var a=JSON.parse(t.getAttribute("data-settings"));if(a.trigger_source)return;for(var c,u=a._animation_delay||a.animation_delay||0,f=0;f<o.length;f++)if(a[o[f]]){o[f],c=a[o[f]];break}if(c){var d=w(t),s="none"===c?d:d+" animated "+c,l=setTimeout((function(){E(t,s.replace(/\belementor\-invisible\b/,"")),o.forEach((function(t){return delete a[t]})),t.setAttribute("data-settings",JSON.stringify(a))}),u);r.on("fi",(function(){clearTimeout(l),E(t,w(t).replace(new RegExp("\b"+c+"\b"),""))}))}}catch(t){console.error(t)}}))}))},S=document,A="querySelectorAll",O="data-in-mega_smartmenus",_="DOMContentLoaded",j="readystatechange",P="message",k=console.error;!function(t,e,n,i,c,u,f,d,s){var l,p,m=t.constructor.name+"::",h=e.constructor.name+"::",y=function(e,n){n=n||t;for(var r=0;r<this.length;r++)e.call(n,this[r],r,this)};"NodeList"in t&&!NodeList.prototype.forEach&&(NodeList.prototype.forEach=y),"HTMLCollection"in t&&!HTMLCollection.prototype.forEach&&(HTMLCollection.prototype.forEach=y),_wpspdn["elementor-animations"]&&L(),_wpspdn["elementor-pp"]&&function(){var t=S.createElement("div");t.innerHTML=\'<span class="sub-arrow --wp-meteor"><i class="fa" aria-hidden="true"></i></span>\';var e=t.firstChild;S.addEventListener("DOMContentLoaded",(function(){Array.from(S[A](".pp-advanced-menu ul")).forEach((function(t){if(!t.getAttribute(O)){(t.getAttribute("class")||"").match(/\bmega\-menu\b/)&&t[A]("ul").forEach((function(t){t.setAttribute(O,!0)}));var n=function(t){for(var e=[];t=t.previousElementSibling;)e.push(t);return e}(t),r=n.filter((function(t){return t})).filter((function(t){return"A"===t.tagName})).pop();if(r||(r=n.map((function(t){return Array.from(t[A]("a"))})).filter((function(t){return t})).flat().pop()),r){var o=e.cloneNode(!0);r.appendChild(o),new MutationObserver((function(t){t.forEach((function(t){t.addedNodes.forEach((function(t){if(1===t.nodeType&&"SPAN"===t.tagName)try{r.removeChild(o)}catch(t){}}))}))})).observe(r,{childList:!0})}}}))}))}();var b,g,w=[],E=[],x={},C=!1,T=!1,M=setTimeout;var N=e[n].bind(e),R=e[i].bind(e),H=t[n].bind(t),D=t[i].bind(t);"undefined"!=typeof EventTarget&&(b=EventTarget.prototype.addEventListener,g=EventTarget.prototype.removeEventListener,N=b.bind(e),R=g.bind(e),H=b.bind(t),D=g.bind(t));var z,q=e.createElement.bind(e),B=e.__proto__.__lookupGetter__("readyState").bind(e);Object.defineProperty(e,"readyState",{get:function(){return z||B()},set:function(t){return z=t}});var Q=function(t){return E.filter((function(e,n){var r=e[0],o=(e[1],e[2]);if(!(t.indexOf(r.type)<0)){o||(o=r.target);try{for(var i=o.constructor.name+"::"+r.type,a=0;a<x[i].length;a++){if(x[i][a])if(!W[i+"::"+n+"::"+a])return!0}}catch(t){}}})).length},W={},I=function(t){E.forEach((function(n,r){var o=n[0],i=n[1],a=n[2];if(!(t.indexOf(o.type)<0)){a||(a=o.target);try{var c=a.constructor.name+"::"+o.type;if((x[c]||[]).length)for(var u=0;u<x[c].length;u++){var f=x[c][u];if(f){var d=c+"::"+r+"::"+u;if(!W[d]){W[d]=!0,e.readyState=i;try{f.hasOwnProperty("prototype")&&f.prototype.constructor!==f?f(o):f.bind(a)(o)}catch(t){k(t,f)}}}}}catch(t){k(t)}}}))};N(_,(function(t){E.push([t,e.readyState,e])})),N(j,(function(t){E.push([t,e.readyState,e])})),H(_,(function(n){E.push([n,e.readyState,t])})),H(d,(function(n){E.push([n,e.readyState,t]),G||I([_,j,P,d])}));var J=function(n){E.push([n,e.readyState,t])};H(P,J),r.on("fi",(function(){T=!0,G=!0,e.readyState="loading",M(X)}));H(d,(function t(){C=!0,T&&!G&&(e.readyState="loading",M(X)),D(d,t)})),(new v).init(_wpspdn.rdelay);var F=new a;F.init();var G=!1,X=function n(){var o=w.shift();if(o)if(o[c]("data-src"))o.hasAttribute("data-async")?(U(o),M(n)):U(o,n);else if("javascript/blocked"==o.type)U(o),M(n);else if(o.hasAttribute("data-wpspdn-onload")){var i=o[c]("data-wpspdn-onload");try{new Function(i).call(o)}catch(t){k(t)}M(n)}else M(n);else if(Q([_,j,P]))I([_,j,P]),M(n);else if(T&&C)if(Q([d,P]))I([d,P]),M(n);else{if(t.RocketLazyLoadScripts)try{RocketLazyLoadScripts.run()}catch(t){k(t)}e.readyState="complete",D(P,J),(x[m+"message"]||[]).forEach((function(t){H(P,t)})),F.unmock(),Z=N,$=R,nt=H,rt=D,G=!1,setTimeout((function(){return r.emit("l")}))}else G=!1},Y=function(t){for(var n=e.createElement("SCRIPT"),r=t.attributes,o=r.length-1;o>=0;o--)n.setAttribute(r[o].name,r[o].value);return n.bypass=!0,n.type=t.hasAttribute("data-wpspdn-module")?"module":"text/javascript",(t.text||"").match(/^\s*class RocketLazyLoadScripts/)?n.text=t.text.replace(/^\s*class RocketLazyLoadScripts/,"window.RocketLazyLoadScripts=class").replace("RocketLazyLoadScripts.run();",""):n.text=t.text,n[f]("data-wpspdn-after"),n},K=function(t,e){var n=t.parentNode;n&&n.replaceChild(e,t)},U=function(t,e){if(t[c]("data-src")){var r=Y(t),o=b?b.bind(r):r[n].bind(r);if(e){var i=function(){return M(e)};o(d,i),o(s,i)}r.src=t[c]("data-src"),r[f]("data-src"),K(t,r)}else"javascript/blocked"===t.type?K(t,Y(t)):onLoad&&onLoad()},V=function(t,e){var n=(x[t]||[]).indexOf(e);if(n>=0)return x[t][n]=void 0,!0},Z=function(t,e){if(e&&(t===_||t===j)){var n=h+t;return x[n]=x[n]||[],void x[n].push(e)}for(var r=arguments.length,o=new Array(r>2?r-2:0),i=2;i<r;i++)o[i-2]=arguments[i];return N.apply(void 0,[t,e].concat(o))},$=function(t,e){t===_&&V(h+t,e);return R(t,e)};Object.defineProperties(e,((l={})[n]={get:function(){return Z},set:function(){return Z}},l[i]={get:function(){return $},set:function(){return $}},l)),r.on("pre",(function(){return w.forEach((function(t){var n=t[c]("data-src");if(n){var r=q("link");r.rel="pre"+d,r.as="script",r.href=n,r.crossorigin=!0,e.head.appendChild(r)}}))})),N(_,(function(){e.querySelectorAll("script[data-wpspdn-after]").forEach((function(t){return w.push(t)}));var t=["link"].map((function(t){return t+"[data-wpspdn-onload]"})).join(",");e.querySelectorAll(t).forEach((function(t){return w.push(t)}))}));var tt=function(t){if(e.currentScript)try{var n=e.currentScript.parentElement,r=e.currentScript.nextSibling,i=document.createElement("div");i.innerHTML=t,Array.from(i.childNodes).forEach((function(t){"SCRIPT"===t.nodeName?n.insertBefore(Y(t),r):n.insertBefore(t,r)}))}catch(t){console.error(t)}else k((new Date-o)/1e3,"document.currentScript not set",t)},et=function(t){return tt(t+"\n")};Object.defineProperties(e,{write:{get:function(){return tt},set:function(t){return tt=t}},writeln:{get:function(){return et},set:function(t){return et=t}}});var nt=function(t,e){if(e&&(t===d||t===_||t===P)){var n=t===_?h+t:m+t;return x[n]=x[n]||[],void x[n].push(e)}for(var r=arguments.length,o=new Array(r>2?r-2:0),i=2;i<r;i++)o[i-2]=arguments[i];return H.apply(void 0,[t,e].concat(o))},rt=function(t,e){t===d&&V(t===_?h+t:m+t,e);return D(t,e)};Object.defineProperties(t,((p={})[n]={get:function(){return nt},set:function(){return nt}},p[i]={get:function(){return rt},set:function(){return rt}},p));var ot=function(t){var e;return{get:function(){return e},set:function(n){return e&&V(t,n),x[t]=x[t]||[],x[t].push(n),e=n}}},it=ot(m+d);Object.defineProperty(t,"onload",it),N(_,(function(){Object.defineProperty(e.body,"onload",it)})),Object.defineProperty(e,"onreadystatechange",ot(h+j)),Object.defineProperty(t,"onmessage",ot(m+P));var at=1,ct=function(){--at||r.emit("i")};H(d,(function t(){M((function(){e.querySelectorAll("img").forEach((function(t){if(!t.complete&&(t.currentSrc||t.src)&&"lazy"==!(t.loading||"").toLowerCase()||(r=t.getBoundingClientRect(),o=window.innerHeight||document.documentElement.clientHeight,i=window.innerWidth||document.documentElement.clientWidth,r.top>=-1*o*1&&r.left>=-1*i*1&&r.bottom<=2*o&&r.right<=2*i)){var e=new Image;e[n](d,ct),e[n](s,ct),e.src=t.currentSrc||t.src,at++}var r,o,i})),ct()})),D(d,t)}));var ut=Object.defineProperty;Object.defineProperty=function(n,r,o){return n===t&&["jQuery","onload"].indexOf(r)>=0||(n===e||n===e.body)&&["readyState","write"].indexOf(r)>=0?n:ut(n,r,o)},Object.defineProperties=function(t,e){for(var n in e)Object.defineProperty(t,n,e[n]);return t}}(window,document,"addEventListener","removeEventListener","getAttribute",0,"removeAttribute","load","error")}]);
</script></head>',$buffer);
        
    $cdnurl = str_replace('https://','',get_option('speedien_cdnurl'));
    
    if(empty($cdnurl))
    {
        $options = get_option('speedien_options');
        $data = array('api_key'=>$options['speedien_field_api_key'], 'site_id' => $options['speedien_field_site_id']);

        $response = wp_remote_post(SPEEDIEN_API_URL . '/cdnurl', array('body' => $data, 'timeout' => 10));
        if(!empty($response['body']))
        {
            $cdnurl = str_replace('https://','',$response['body']);
        }
        else
        {
            $cdnurl = 'nocdn';
        }
        update_option('speedien_cdnurl',$cdnurl);
    }
    
    if(defined('SPEEDIEN_CUSTOM_CDN'))
    {
        $cdnurl = 'nocdn';
    }

    if(defined('CDN_SITE_ID'))
    {
        $cdnurl = 'nocdn';
    }
        
    if($cdnurl!== 'nocdn')
    {
        $site_url = str_replace('https://','',get_site_url());
        $buffer = str_replace($site_url.'/wp-content',$cdnurl.'/wp-content',$buffer);
        $buffer = str_replace($site_url.'\/wp-content',$cdnurl.'\/wp-content',$buffer);
        $buffer = str_replace($site_url.'/wp-includes',$cdnurl.'/wp-includes',$buffer);
    }
    if(!defined('SPEEDIEN_CSS'))
    {
        $buffer = str_replace('</head>','<img alt="Placeholder canvas" width="2100" height="2100" style="pointer-events: none; position: absolute; top: 0; left: 0; width: 90vw; height: 99vh; max-width: 90vw; max-height: 99vh;"  src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIHdpZHRoPSI5OTk5OXB4IiBoZWlnaHQ9Ijk5OTk5cHgiIHZpZXdCb3g9IjAgMCA5OTk5OSA5OTk5OSIgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj48ZyBzdHJva2U9Im5vbmUiIGZpbGw9Im5vbmUiIGZpbGwtb3BhY2l0eT0iMCI+PHJlY3QgeD0iMCIgeT0iMCIgd2lkdGg9Ijk5OTk5IiBoZWlnaHQ9Ijk5OTk5Ij48L3JlY3Q+IDwvZz4gPC9zdmc+"></head>',$buffer);
    }
    return $buffer;
}

add_action( 'template_redirect', 'speedien_process_page');

function speedien_exclusion_handler($replace, $string ) {
    $options = get_option('speedien_options');
    $jse_type = 1;
    if(!empty($options['speedien_field_jse_type']))
    {
        $jse_type = $options['speedien_field_jse_type'];
    }
    if($jse_type == 1)
    {
        if(!empty($options['speedien_field_jslist']))
        {
            $js_exclusions = explode(PHP_EOL, $options['speedien_field_jslist']);
            foreach($js_exclusions as $exclusion)
            {
                if(strpos($string, trim($exclusion)) !== false)
                {
                    return $string;
                }
                if(strpos('exclude_all', trim($exclusion)) !== false)
                {
                    return true;
                }
            }
        }
        return false;
    }
    else
    {
        if(!empty($options['speedien_field_jslist']))
        {
            $js_exclusions = explode(PHP_EOL, $options['speedien_field_jslist']);
            foreach($js_exclusions as $exclusion)
            {
                if(strpos($string, trim($exclusion)) !== false)
                {
                    return false;
                }
            }
            return true;
        }
    }
    
}
add_filter( 'wpspdn_exclude', 'speedien_exclusion_handler', 10, 3 );