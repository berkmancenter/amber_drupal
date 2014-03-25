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
    return (jQuery("a[data-cache][data-cayl-behavior*=\\,]").length > 0);
  },

  get_country : function() {
    try {
      if (!cayl.country) {
        cayl.country = localStorage.getItem('country');
      }
    } catch (e) { /* Not supported */ }
    if (!cayl.country) {
      jQuery.getJSON('http://freegeoip.net/json/?callback=?', function(data) {
        cayl.country = data.country_code;
        try {
          if (cayl.country) {
            localStorage.setItem('country',cayl.country);
          }
        } catch (e) { /* Not supported */ }
      });
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
        //TODO: Parse source-specific items
      }
    }
    return result;
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
    var behavior = cayl.parse_behavior(jQuery(this).attr("data-cayl-behavior"));
    var cache = cayl.parse_cache(jQuery(this).attr("data-cache"));

    if (cayl.execute_action(behavior,"cache") && cache.default) {
      window.location.href = cache.default.cache;
      return false;
    } else {
      return true;
    }
  },

  show_interstitial : function (e) {
    var behavior = cayl.parse_behavior(jQuery(this).attr("data-cayl-behavior"));
    var cache = cayl.parse_cache(jQuery(this).attr("data-cache"));

    if (cayl.execute_action(behavior,"popup") && cache.default) {
      /* Add the window to the DOM */
      jQuery("body").append('<div class="cayl-overlay"></div>');

      /* Substitute dynamic text */
      var replacements = {
        '{{DATE}}' : new Date(cache.default.date).toLocaleDateString(),
        '{{NAME}}' : (cayl.name == undefined) ? "This site" : cayl.name,
        '{{CACHE}}' : cache.default.cache,
        '{{LINK}}' : jQuery(this).attr("href")
      }

      jQuery("body").append(cayl.replace_args(cayl.get_text('interstitial_html'), replacements));

      /* Center the window */
      var left = jQuery(window).width()/2 - jQuery(".cayl-interstitial").width()/2;
      var top =  jQuery(window).height()/2 - jQuery(".cayl-interstitial").height()/2;
      jQuery(".cayl-interstitial").css({"left" : left, "top": top});

      /* Clicking on the overlay or close button closes the window */
      jQuery(".cayl-overlay, .cayl-close").click(function(e) { jQuery(".cayl-overlay, .cayl-interstitial").remove(); return false; });

      return false;
    } else {
      return true;
    }
  },

  start_popup_hover : function (e) {
    cayl.hovering_on_popup = true;
  }, 

  end_popup_hover : function (e) {
    cayl.hovering_on_popup = false;   
    jQuery(".cayl-hover").remove();
  },                        
                                            
  calculate_hover_position : function (target, status) {
    var offset = jQuery(target).offset();
    var result = {};
    result = {"left" : offset.left - 30, "top" : offset.top - 105}
    if (cayl.rtl) {
      result.left = result.left + jQuery(target).width() - jQuery(".cayl-hover").width();
    }          
    return result;
  },

  start_link_hover : function (e) {
    var behavior = cayl.parse_behavior(jQuery(this).attr("data-cayl-behavior"));

    if (cayl.execute_action(behavior,"hover")) {
      var cache = cayl.parse_cache(jQuery(this).attr("data-cache"));
      var args = {
        '{{DATE}}' : new Date(cache.default.date).toLocaleDateString(),
        '{{NAME}}' : (cayl.name == undefined) ? "This site" : cayl.name,
        '{{CACHE}}' : cache.default.cache,
        '{{LINK}}' : jQuery(this).attr("href")
      };
      t = this;
      if (behavior.default.delay) {
        var timer = setTimeout(function() {
          jQuery("body").append(cayl.replace_args(behavior.default.status == "up" ? cayl.get_text('hover_html_up') : cayl.get_text('hover_html_down'), args));

          /* Position the hover text */
          var offset = jQuery(t).offset();
          jQuery(".cayl-hover").css(cayl.calculate_hover_position(t, behavior.default.status));
          jQuery(".cayl-hover").hover(cayl.start_popup_hover, cayl.end_popup_hover);
        }, behavior.default.delay * 1000);
        jQuery(this).attr("cayl-timer",timer);
      }
    }
  },

  end_link_hover : function (e) {
    clearTimeout(jQuery(this).attr("cayl-timer"));         

    /* Give them some time, and then check if they've moved over the popup before closing popup */
    setTimeout(function() {
      if (!cayl.hovering_on_popup) {
        jQuery(".cayl-hover").remove();
      }    
    },100);
  }
};

jQuery(document).ready(function($) {
    $("a[data-cache][data-cayl-behavior*=cache]").click(cayl.show_cache);
    $("a[data-cache][data-cayl-behavior*=popup]").click(cayl.show_interstitial);
    $("a[data-cache][data-cayl-behavior*=hover]").hover(cayl.start_link_hover, cayl.end_link_hover);

    if (cayl.country_specific_behavior_exists()) {
      cayl.get_country();
    }

    /* Drupal-specific code */
    cayl.name = Drupal.settings.cayl.name;
  });

