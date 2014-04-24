var cayl = {

  hovering_on_popup : false,
  locale : 'en',
  country : '',
  rtl : false,
  translations : {
    en : {
      interstitial_html :
      '<div class="cayl-interstitial"><a href="#" class="cayl-close"></a><div class="cayl-body"><div class="cayl-status-text">This page may not be available</div><div class="cayl-cache-text">{{NAME}} has a cache from {{DATE}}</div>' +
      '<a class="cayl-focus" href="{{CACHE}}">View the cache</a><div class="cayl-iframe-container"><iframe src="{{LINK}}"/></div><a class="cayl-original-link" href="{{LINK}}">Continue to the page</a></div><a class="cayl-info" href="http://brk.mn/robustness" target="_blank">i</a></div>',
      hover_html_up   : '<div class="cayl-hover cayl-up"><a class="cayl-info" href="http://brk.mn/robustness" target="_blank">i</a><div class="cayl-text"><div class="cayl-status-text">This page should be available</div><div class="cayl-cache-text">{{NAME}} has a cache from {{DATE}}</div></div><div class="cayl-links"><a href="{{CACHE}}">View the cache</a><a href="{{LINK}}" class="cayl-focus">Continue to the page</a></div><div class="cayl-arrow"></div></div>',
      hover_html_down : '<div class="cayl-hover cayl-down"><a class="cayl-info" href="http://brk.mn/robustness" target="_blank">i</a><div class="cayl-text"><div class="cayl-status-text">This page may not be available</div><div class="cayl-cache-text">{{NAME}} has a cache from {{DATE}}</div></div><div class="cayl-links"><a href="{{CACHE}}" class="cayl-focus">View the cache</a><a href="{{LINK}}">Continue to the page</a></div><div class="cayl-arrow"></div></div>'
    },
    fa : {
        interstitial_html :
        '<div class="cayl-interstitial"><a href="#" class="cayl-close"></a><div class="cayl-body"><div class="cayl-message"><span>لینکی که کلیک کردید ممکن است در دسترس برای مشاهده شود.</span><br/>ما پیدا کردن یک نسخه ذخیره سازی این صفحه.</div>' +
        '<div class="cayl-cached"><div>پیوند زندگی می کنند ممکن است به صفحه شما به دنبال نمی شود و ممکن است جایگزین شده است و یا روت به سرور دیگری.<div>اتصال به مخزن ما این است که از<br/>  ' +
        '{{DATE}}</div></div><a href="{{CACHE}}">نمایش لینک های cache شده</a></div><div class="cayl-live"><div><iframe src="{{LINK}}"/></div><a href="{{LINK}}">نمایش لینک های فعال</a></div>' +
        '<div class="cayl-credit">بالاترین از <a href="#"> CAYL </ A></div></div></div>',
        hover_html_up : '<div class="cayl-hover cayl-up"><div class="cayl-text">این سایت باید در دسترس باشد</div><a href="{{LINK}}" class="cayl-live">دیدن لینک زنده</a><a href="{{CACHE}}" class="cayl-cache">دیدن لینک خرید پستی</a><div class="cayl-arrow"></div></div>',
        hover_html_down : '<div class="cayl-hover cayl-down"><div class="cayl-text">این سایت ممکن است در دسترس</div><a href="{{LINK}}" class="cayl-live">دیدن لینک زنده</a><a href="{{CACHE}}" class="cayl-cache">دیدن لینک خرید پستی</a><div class="cayl-credit"> بالاترین از <a href="#"> CAYL </a></div><div class="cayl-arrow"></div></div>',
      }
    },

  set_locale : function(locale) {
    cayl.locale = locale;
    cayl.rtl = (locale == 'fa');
  },

  country_specific_behavior_exists : function() {
    return (document.querySelectorAll("a[data-cache][data-cayl-behavior*=\\,]").length > 0);
  },

  callback : function(json) {
    try {
      cayl.country = json.country_code;
      if (cayl.country) {
        localStorage.setItem('country',cayl.country);
      }
    } catch (e) { /* Not supported */ }
  },

  get_country : function() {
    try {
      if (!cayl.country) {
        cayl.country = localStorage.getItem('country');
      }
    } catch (e) { /* Not supported */ }
    if (!cayl.country) {
      var script = document.createElement('script');
      script.src = '//freegeoip.net/json/?callback=cayl.callback';
      document.getElementsByTagName('head')[0].appendChild(script);
    }
  },

  get_text : function(key) {
    return cayl.translations[cayl.locale][key];
  },

  replace_args : function (s, args) {
    for (var key in args) {
      s = s.replace(new RegExp(key,"g"), args[key]);
    }
    return s;
  },

  parse_country_behavior : function(s) {
    var result = {};
    var x = s.split(" ");
    if (x.length != 2) {
      return false;
    } else {
      result.status = x[0];
      y = x[1].split(":");
      switch (y.length) {
        case 1:
          result.action = y[0];
          break;
        case 2:
          result.action = y[0];
          result.delay = y[1];
          break;
      }
    }
    return result;
  },

  parse_behavior : function(s) {
    var result = {};
    /* Split by country */
    var countries = s.split(",");
    result.default = cayl.parse_country_behavior(countries[0]);
    if (countries.length > 1) {
      for (i = 1; i < countries.length; i++) {
        var x = countries[i].split(' ');
        var c = x.shift();
        if (x.length == 2) {
          result[c] = cayl.parse_country_behavior(x.join(' '));
        }
      }
    }
    return result;
  },

  parse_cache_source : function(s) {
    var result = {};
    var x = s.split(" ");
    if (x.length != 2) {
      return false;
    } else {
      result.cache = x[0];
      result.date = x[1];
    }
    return result;
  },

  parse_cache : function(s) {
    var result = {};
    /* Split by cache source */
    var sources = s.split(",");
    result.default = cayl.parse_cache_source(sources[0]);
    if (sources.length > 1) {
      for (i = 0; i < sources.length; i++) {
        // Logic for additional cache sources will go here
      }
    }
    return result;
  },

  format_date_from_string : function(s) {
      var a = s.split(/[^0-9]/);
      return new Date (a[0],a[1]-1,a[2],a[3],a[4],a[5]).toLocaleDateString();
  },

  execute_action: function (behavior, action) {
    if (!cayl.country && behavior.default.action == action) {
      return true;
    }
    if (cayl.country && !(cayl.country in behavior) && (behavior.default.action == action)) {
      return true;
    }
    if (cayl.country && (behavior[cayl.country].action == action)) {
      return true;
    }
    return false;
  },

  show_cache : function(e) {
    var behavior = cayl.parse_behavior(this.getAttribute("data-cayl-behavior"));
    var cache = cayl.parse_cache(this.getAttribute("data-cache"));
    if (cayl.execute_action(behavior,"cache") && cache.default) {
      window.location.href = cache.default.cache;
      e.preventDefault();
    }
  },

  show_interstitial : function (e) {
    var behavior = cayl.parse_behavior(this.getAttribute("data-cayl-behavior"));
    var cache = cayl.parse_cache(this.getAttribute("data-cache"));

    if (cayl.execute_action(behavior,"popup") && cache.default) {
      /* Add the window to the DOM */
      var element = document.createElement('div');
      element.className = "cayl-overlay";
      document.body.appendChild(element);

      /* Substitute dynamic text */
      var replacements = {
        '{{DATE}}' : cayl.format_date_from_string(cache.default.date),
        '{{NAME}}' : (cayl.name == undefined) ? "This site" : cayl.name,
        '{{CACHE}}' : cache.default.cache,
        '{{LINK}}' : this.getAttribute("href")
      }

      var caylElement = document.createElement('div');
      caylElement.innerHTML = cayl.replace_args(cayl.get_text('interstitial_html'), replacements);
      document.body.appendChild(caylElement.firstChild);

      /* Center the window */
      var w = window;
      var d = document;
      var el = d.documentElement;
      var g = d.getElementsByTagName('body')[0];
      var windowWidth = w.innerWidth || el.clientWidth || g.clientWidth;
      var windowHeight = w.innerHeight|| el.clientHeight|| g.clientHeight;
      var interstitial = document.querySelectorAll(".cayl-interstitial")[0];

      var left = windowWidth/2 - interstitial.offsetWidth/2;
      var top =  windowHeight/2 - interstitial.offsetHeight/2;
      interstitial.style.left = left + "px";
      interstitial.style.top = top + "px";

      /* Clicking on the overlay or close button closes the window */
      var closeEls = document.querySelectorAll(".cayl-overlay, .cayl-close");
      for (var i = 0; i < closeEls.length; i++) {
        cayl.util_addEventListener(closeEls[i],'click',function(e) {
          var els = document.querySelectorAll(".cayl-overlay, .cayl-interstitial");
          for (var i = 0; i < els.length; i++) {
              els[i].parentNode.removeChild(els[i]);
          }
          e.preventDefault();
        });
      }
      e.preventDefault();
    }
  },

  start_popup_hover : function (e) {
    cayl.hovering_on_popup = true;
  },

  end_popup_hover_function : function (hover) {
    // Need to make sure that we're not hovering over one of the child elements.
    // Return a function that captures the original node's descendants
    var descendants = hover.querySelectorAll('*');
    return function (e) {
      var el = e.toElement || e.relatedTarget;
      for (var i = 0; i < descendants.length; i++) {
        if (el == descendants[i]) {
          return;
        }
      }
      cayl.hovering_on_popup = false;
      var hover = document.querySelectorAll(".cayl-hover")[0];
      hover.parentNode.removeChild(hover);
    }
  },

  calculate_hover_position : function (target, status) {
    var offset = cayl.util_offset(target);
    var result = {"left" : offset.left - 30, "top" : offset.top - 105}
    if (cayl.rtl) {
      var hover = document.querySelectorAll(".cayl-hover")[0];
      result.left = result.left + target.offsetWidth - hover.offsetWidth;
    }
    return result;
  },

  start_link_hover : function (e) {
    var behavior = cayl.parse_behavior(this.getAttribute("data-cayl-behavior"));
    if (cayl.execute_action(behavior,"hover")) {
      var cache = cayl.parse_cache(this.getAttribute("data-cache"));
      var args = {
        '{{DATE}}' : cayl.format_date_from_string(cache.default.date),
        '{{NAME}}' : (cayl.name == undefined) ? "This site" : cayl.name,
        '{{CACHE}}' : cache.default.cache,
        '{{LINK}}' : this.getAttribute("href")
      };
      t = this;
      var delay = behavior[cayl.country] ? behavior[cayl.country].delay : behavior.default.delay;
      var timer = setTimeout(function() {
        var caylElement = document.createElement('div');
        caylElement.innerHTML = cayl.replace_args(behavior.default.status == "up" ? cayl.get_text('hover_html_up') : cayl.get_text('hover_html_down'), args);
        document.body.appendChild(caylElement.firstChild);

        /* Position the hover text */
        var hover = document.querySelectorAll(".cayl-hover")[0];
        var pos = cayl.calculate_hover_position(t, behavior.default.status);
        hover.style.left = pos.left + "px";
        hover.style.top = pos.top + "px";
        cayl.util_addEventListener(hover, 'mouseover', cayl.start_popup_hover);
        cayl.util_addEventListener(hover, 'mouseout', cayl.end_popup_hover_function(hover));
      }, delay * 1000);
      this.setAttribute("cayl-timer",timer);
    }
  },

  end_link_hover : function (e) {
    var behavior = cayl.parse_behavior(this.getAttribute("data-cayl-behavior"));
    if (cayl.execute_action(behavior,"hover")) {
      clearTimeout(this.getAttribute("cayl-timer"));

      /* Give them some time, and then check if they've moved over the popup before closing popup */
      setTimeout(function() {
        if (!cayl.hovering_on_popup) {
          var hover = document.querySelectorAll(".cayl-hover")[0];
          if (typeof hover != typeof undefined)
            hover.parentNode.removeChild(hover);
        }
      },100);
    }
  },

  /* Utility functions to provide support for IE8+ */
  util_addEventListener : function (el, eventName, handler) {
    if (el.addEventListener) {
      el.addEventListener(eventName, handler);
    } else {
      el.attachEvent('on' + eventName, function(){
        handler.call(el);
      });
    }
  },

  util_forEachElement : function (selector, fn) {
    var elements = document.querySelectorAll(selector);
    for (var i = 0; i < elements.length; i++)
       fn(elements[i], i);
  },

  util_ready : function (fn) {
    if (document.addEventListener) {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      document.attachEvent('onreadystatechange', function() {
        if (document.readyState === 'interactive')
          fn();
      });
    }
  },

  util_offset : function(elem) {
      var box = { top: 0, left: 0 };
      var doc = elem && elem.ownerDocument;
      var docElem = doc.documentElement;
      if (typeof elem.getBoundingClientRect !== typeof undefined ) {
          box = elem.getBoundingClientRect();
      }
      var win = (doc != null && doc=== doc.window) ? doc: doc.nodeType === 9 && doc.defaultView;
      return {
          top: box.top + win.pageYOffset - docElem.clientTop,
          left: box.left + win.pageXOffset - docElem.clientLeft
      };
  }


};

cayl.util_ready(function($) {

    cayl.util_forEachElement("a[data-cache][data-cayl-behavior*=cache]", function(e, i) {
      cayl.util_addEventListener(e, 'click', cayl.show_cache);
    });
    cayl.util_forEachElement("a[data-cache][data-cayl-behavior*=popup]", function(e, i) {
      cayl.util_addEventListener(e, 'click', cayl.show_interstitial);
    });
    cayl.util_forEachElement("a[data-cache][data-cayl-behavior*=hover]", function(e, i) {
      cayl.util_addEventListener(e, 'mouseover', cayl.start_link_hover);
      cayl.util_addEventListener(e, 'mouseout', cayl.end_link_hover);
    });

    if (cayl.country_specific_behavior_exists()) {
      cayl.get_country();
    }

    /* Drupal-specific code */
    if (typeof Drupal != 'undefined') {
      cayl.name = Drupal.settings.cayl.name;
    }
});
