(function() {
  var __bind = function(fn, me){ return function(){ return fn.apply(me, arguments); }; }, __slice = Array.prototype.slice;

  (function($, window) {
    var PanoptoTac;
    PanoptoTac = (function() {

      PanoptoTac.prototype.defaults = {
        dialogAnch: 'body',
        dialogId: 'panoptotac_dialog',
        pageWidth: 540
      };

      function PanoptoTac(el, options) {
        this.acceptTerms = __bind(this.acceptTerms, this);
        this.loadTermsPage = __bind(this.loadTermsPage, this);
        this.loadChoicePage = __bind(this.loadChoicePage, this);
        this.cancelDialog = __bind(this.cancelDialog, this);
        this.openDialog = __bind(this.openDialog, this);
        this.buttonDelegates = __bind(this.buttonDelegates, this);
        this.setUpViews = __bind(this.setUpViews, this);        this.isOpen = false;
        this.options = $.extend({}, this.defaults, options);
        this.el = el;
        this.page = 0;
        this.isAnimating = false;
        this.strings = {
          role_choice_head: M.str.block_panopto.role_choice_head,
          role_choice_ac_btn: M.str.block_panopto.role_choice_ac_btn,
          role_choice_nac_btn: M.str.block_panopto.role_choice_nac_btn,
          role_choice_cancel: M.str.block_panopto.role_choice_cancel,
          terms_head: M.str.block_panopto.terms_head,
          terms_back_btn: M.str.block_panopto.terms_back_btn,
          terms_agree_btn: M.str.block_panopto.terms_agree_btn,
          terms_decline_btn: M.str.block_panopto.terms_decline_btn,
          accademic_terms: M.str.block_panopto.accademic_terms,
          non_accademic_terms: M.str.block_panopto.non_accademic_terms,
          success_roleassign: M.str.block_panopto.success_roleassign,
          success_sync_succ: M.str.block_panopto.success_sync_succ,
          success_sync_fail: M.str.block_panopto.success_sync_fail,
          success_extras: M.str.block_panopto.success_extras,
          error: M.str.block_panopto.error
        };
        this.setUpViews();
        this.loadChoicePage();
        this.el.bind({
          click: this.openDialog
        });
        this.buttonDelegates();
      }

      PanoptoTac.prototype.setUpViews = function() {
        this.dialog_bg = _.template('<div id="panoptotac_dialog_overlay"></div>');
        this.dialog_box = _.template("<div id='<%= dialogId %>'>										<div class='panoptotac_wrap'>											<a href='#' id='panoptotac_dialog_cancel'>x</a> 											<div class='panoptotac_content_wrap'>											</div>																				  	</div>									  </div>");
        this.dialog_box_content = _.template("<div class='panoptotac_page <%= clas %>'>												<h2 class='panoptotac_head'><%= title %></h2>												<div class='panoptotac_content'><%= content %></div>												<div class='panoptotac_foot'><%= foot %></div>											</div>");
        $(this.options.dialogAnch).append(this.dialog_bg);
        $(this.options.dialogAnch).append(this.dialog_box({
          dialogId: this.options.dialogId
        }));
        $('.panoptotac_content_wrap').width(this.options.pageWidth * 2);
        this.dlgOver = $('#panoptotac_dialog_overlay');
        return this.dlg = $('#' + this.options.dialogId);
      };

      PanoptoTac.prototype.buttonDelegates = function() {
        var _this = this;
        $(this.dlg).on('click', '#panoptotac_dialog_cancel, .panoptotac_cancel, .panoptotac_dec', this.cancelDialog);
        $(this.dlg).on('click', '.panoptotac_choice_btn', function(e) {
          e.preventDefault();
          return _this.loadTermsPage(this);
        });
        $(this.dlg).on('click', '.panoptotac_back', function(e) {
          e.preventDefault();
          return _this.loadChoicePage();
        });
        $(this.dlg).on('click', '.panoptotac_acc', this.acceptTerms);
        return $(this.dlg).on('click', '.panoptotac_terms_box a', function(e) {
          var parent, target;
          if ($(this).attr('href')[0] === '#') {
            e.preventDefault();
            target = $($(this).attr('href')).get(0);
            parent = $(target).closest('.panoptotac_terms_box').get(0);
            return parent.scrollTop = target.offsetTop;
          }
        });
      };

      PanoptoTac.prototype.openDialog = function(e) {
        e.preventDefault();
        if (!this.isOpen) {
          $('body').addClass('panoptotac_scroll_disable');
          this.dlgOver.show();
          this.dlg.show();
          return this.isOpen = true;
        }
      };

      PanoptoTac.prototype.cancelDialog = function(e) {
        e.preventDefault();
        if (this.isOpen) {
          $('body').removeClass('panoptotac_scroll_disable');
          this.dlgOver.hide();
          this.dlg.hide();
          this.isOpen = false;
          if (this.page !== 1) return this.loadChoicePage();
        }
      };

      PanoptoTac.prototype.loadChoicePage = function() {
        var dialog_tac_choice, dialog_tac_choice_foot;
        var _this = this;
        if (this.isAnimating || this.page === 1) return;
        if (this.page === 2) {
          this.isAnimating = true;
          return $('.panoptotac_content_wrap').animate({
            left: 0
          }, 'fast', 'easeOutExpo', function() {
            _this.isAnimating = false;
            _this.page = 1;
            return $('.panoptotac_terms').remove();
          });
        } else {
          dialog_tac_choice = "<a href='#' class='panoptotac_academic_staff_btn panoptotac_choice_btn' data-type='ac'>" + this.strings.role_choice_ac_btn + "</a>" + "<a href='#' class='panoptotac_non_academic_staff_btn panoptotac_choice_btn' data-type='nac'>" + this.strings.role_choice_nac_btn + "</a>";
          dialog_tac_choice_foot = '<a href="#" class="panoptotac_cancel panoptotac_btn">' + this.strings.role_choice_cancel + '</a>';
          $('.panoptotac_content_wrap').append(this.dialog_box_content({
            clas: 'panoptotac_choice',
            title: this.strings.role_choice_head,
            content: dialog_tac_choice,
            foot: dialog_tac_choice_foot
          }));
          return this.page = 1;
        }
      };

      PanoptoTac.prototype.loadTermsPage = function(button) {
        var dialog_tac_terms_content, dialog_tac_terms_foot;
        var _this = this;
        if (!this.isAnimating) {
          this.isAnimating = true;
          this.page = 2;
          this.role = $(button).data('type');
          dialog_tac_terms_content = "<div class='panoptotac_terms_box'>" + (this.role === 'ac' ? this.strings.accademic_terms : this.strings.non_accademic_terms) + "</div>";
          dialog_tac_terms_foot = "<a href='#' class='panoptotac_back panoptotac_btn'>" + this.strings.terms_back_btn + "</a>										<div class='panoptotac_submit_wrap'>											<a href='#' class='panoptotac_btn panoptotac_dec'>" + this.strings.terms_decline_btn + "</a>											<a href='#' class='panoptotac_btn panoptotac_acc'>" + this.strings.terms_agree_btn + "</a>										</div>";
          return $('.panoptotac_content_wrap').append(this.dialog_box_content({
            clas: 'panoptotac_terms',
            title: this.strings.terms_head,
            content: dialog_tac_terms_content,
            foot: dialog_tac_terms_foot
          })).animate({
            left: '-' + this.options.pageWidth
          }, 'fast', 'easeOutExpo', function() {
            return _this.isAnimating = false;
          });
        }
      };

      PanoptoTac.prototype.acceptTerms = function(e) {
        var loading, __this;
        e.preventDefault();
        __this = this;
        loading = "<div class='panoptotac_loading'>Submitting</div>";
        return $('.panoptotac_terms').fadeOut(function() {
          var _this = this;
          $('div, h2', this).html('');
          $('.panoptotac_terms .panoptotac_content').html(loading);
          $(this).fadeIn();
          return $.ajax(M.cfg.wwwroot + '/blocks/panopto/accept_terms.php', {
            data: {
              role: __this.role,
              course: __this.options.courseid
            },
            dataType: 'json',
            success: function(result) {
              var success;
              $('.panoptotac_loading', _this).fadeOut();
              success = "<div class='panoptotac_success'>Success<span>" + ("" + __this.strings.success_roleassign) + ("" + (result.course_provision ? __this.strings.success_sync_succ : __this.strings.success_sync_fail)) + ("" + __this.strings.success_extras) + "</span></div>";
              $('.panoptotac_terms .panoptotac_content').hide(function() {
                return $(this).html(success).fadeIn();
              });
              return $('#panopto_perm_state').html('Access: Creator');
            },
            error: function(result) {
              var error;
              $('.panoptotac_loading', _this).fadeOut();
              error = "<div class='panoptotac_error'>Error<span>" + ("" + __this.strings.error) + "</span></div>";
              return $('.panoptotac_terms .panoptotac_content').hide(function() {
                return $(this).html(error).fadeIn();
              });
            }
          });
        });
      };

      return PanoptoTac;

    })();
    return $.fn.extend({
      panoptoTac: function() {
        var args, obj, options;
        options = arguments[0], args = 2 <= arguments.length ? __slice.call(arguments, 1) : [];
        if (this.length > 1) $.error('PanoptoTac can only be initialized once');
        obj = this.data('panoptoTac');
        if (!obj && typeof options !== 'string') {
          return this.data('panoptoTac', (obj = new PanoptoTac(this, options)));
        } else if (typeof options === 'string') {
          return obj[options].apply(obj, args);
        } else {
          return $.error('Incorrect usage of panoptoTac plugin');
        }
      }
    });
  })(window.jQuery, window);

}).call(this);
