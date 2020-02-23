<?php

namespace WPLF;


class Form extends Module {
  public static $postType = 'libreform';

  public function __construct(Plugin $wplf) {
    $this->injectCore($wplf);
    log("form");


    // init custom post type
    add_action('init', [$this, 'registerCpt']);

    // post.php / post-new.php view
    // add_filter('get_sample_permalink_html', [$this, 'modifyPermalinkHtml'], 10, 2);
    add_action('save_post', [$this, 'saveCpt']);
    add_filter('content_save_pre', [$this, 'stripFormTags'], 10, 1);
    add_action('add_meta_boxes', [$this, 'addMetaBoxesCpt']);
    add_action('add_meta_boxes', [$this, 'maybeLoadImportedTemplate'], 10, 2);
    add_action('admin_notices', [$this, 'printNotices'], 10);
    add_action('delete_post', [$this, 'deleteForm']);

    // edit.php view
    add_filter('post_row_actions', [$this, 'removeRowActions'], 10, 2);
    add_filter('manage_edit-' . self::$postType . '_columns', [$this, 'customColumns'], 100, 1);
    add_action('manage_' . self::$postType . '_posts_custom_column', [$this, 'customColumnsContents'], 10, 2);

    add_filter('default_content', [$this, 'defaultContent']);
    add_filter('user_can_richedit', [$this, 'disableTinymce']);
    add_filter('use_block_editor_for_post_type', [$this, 'disableGutenberg'], 10, 2);

    // frontend
    add_shortcode('libreform', [$this, 'shortcode']);
    add_action('wp', [$this, 'maybeFake404']);
    add_filter('the_content', [$this, 'replaceContentWithFormOnSingleForm'], 0);
    // add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueFrontendScript']);

    // default filters for the_content, but we don't want to use actual the_content
    add_filter('wplfBeforeRender', 'convert_smilies');
    add_filter('wplfBeforeRender', 'convert_chars');
    add_filter('wplfBeforeRender', 'shortcode_unautop');

    // we want to keep form content strictly html, so let's remove auto <p> tags
    remove_filter('wplf_form', 'wpautop');
    remove_filter('wplf_form', 'wptexturize');

    // Removing wpautop isn't enough if form is used inside a ACF field or so.
    // Fitting the output to one line prevents <br> tags from appearing.
    add_filter('wplfBeforeRender', '\WPLF\minifyHtml', PHP_INT_MAX);

    // before delete, remove the possible uploads
    add_action('before_delete_post', [$this, 'cleanUpEntry']);
  }

  public static function registerCpt() {
    $args = [
      'labels' => [
        'name' => _x('Forms', 'post type general name', 'libreform'),
        'singular_name' => _x('Form', 'post type singular name', 'libreform'),
        'menu_name' => _x('Forms', 'admin menu', 'libreform'),
        'name_admin_bar' => _x('Form', 'add new on admin bar', 'libreform'),
        'add_new' => _x('New Form', 'form', 'libreform'),
        'add_new_item' => __('Add New Form', 'libreform'),
        'new_item' => __('New Form', 'libreform'),
        'edit_item' => __('Edit Form', 'libreform'),
        'view_item' => __('View Form', 'libreform'),
        'all_items' => __('All Forms', 'libreform'),
        'search_items' => __('Search Forms', 'libreform'),
        'not_found' => __('No forms found.', 'libreform'),
        'not_found_in_trash' => __('No forms found in Trash.', 'libreform'),
      ],
      'public'              => true,
      'publicly_queryable'  => true,
      'exclude_from_search' => true,
      'show_ui'             => true,
      'show_in_menu'        => true,
      'menu_icon'           => 'dashicons-archive',
      'query_var'           => false,
      'capability_type'     => 'post',
      'has_archive'         => false,
      'hierarchical'        => false,
      'menu_position'       => null,
      'rewrite'             => [
        'slug' => 'libreforms',
      ],
      'supports'            => array(
        'title',
        'editor',
        'revisions',
        'custom-fields',
     ),
      'show_in_rest' => true,
    ];

    register_post_type(self::$postType, $args);
  }

  /**
   * Modify post.php permalink html to show notice if form isn't publicly visible.
   */
  // public function modifyPermalinkHtml($html, $post_id) {
  //   $publicly_visible = $this->getPubliclyVisibleState($post_id);

  //   if (get_post_type($post_id) === self::$postType && !$publicly_visible) {
  //     $html .= '<span>';
  //     $html .= __('Permalink is for preview purposes only.', 'libreform');
  //     $html .= '</span>';
  //   }

  //   return $html;
  // }

  /**
   * Disable TinyMCE editor for forms, which are simple HTML things
  */
  public function disableTinymce($value) {
    if (self::$postType === get_post_type()) {
      return false;
    }

    return $value;
  }

  /**
   *  Disable Gutenberg
  */
  public function disableGutenberg($value, $post_type) {
    if (self::$postType === $post_type) {
      return false;
    }

    return $value;
  }

  /**
   * Berore permanently deleting form entry, remove attachments
   * in the case they were not added to media library
   */
  public function cleanUpEntry($id) {
    $type = get_post_type($id);
    if ('wplf-submission' === $type) {
      $postmeta = get_post_meta($id);

      foreach ($postmeta as $key => $meta) {
          $m = $meta[0];
        if (strpos($key, 'attachment') !== false) {
          $path = str_replace(WP_HOME . '/', get_home_path(), $m);
          unlink($path);
        }
      }
    }
  }

  public function printNotices() {
    $post_id = ! empty($_GET['post']) ? (int) $_GET['post'] : false;
    $type = get_post_type($post_id);

    $unableToEdit = is_multisite() && !current_user_can('unfiltered_html');

    if ($type !== self::$postType || ! $post_id) {
      return false;
    }

    $version_created_at = get_post_meta($post_id, '_wplf_plugin_version', true);
    $version_created_at = $version_created_at ? $version_created_at : '< 1.5';

    if ($unableToEdit) { ?>
    <div class="notice notice-error">
      <p>
      <?php
        echo esc_html(
            __(
                'Your site is part of a WordPress Network.
            Network installations are different from standard WordPress sites,
            and you need unfiltered_html capability to be able to save anything with HTML.',
                'libreform'
           )
       ); ?>
      </p>

      <p>
      <?php
        echo esc_html(
            __(
                'You do not have this capability, so to prevent you from accidentally destroying the form, you can\'t save here.
            Either switch to a user with Super Admin role, or install a plugin like Unfiltered HTML.',
                'libreform'
           )
       ); ?>
      </p>
    </div>
    <?php
    }

    // The notice prints outside the form element
    //  a hidden field is created or deleted when this checkbox changes
    if (version_compare($version_created_at, WPLF_VERSION, '<')) { ?>
    <div class="notice notice-info">
      <p>
      <?php echo sprintf(
          esc_html(
              // translators: Placeholders indicate version numbers
              __('This form was created with WPLF version %1$s, and your installed WPLF version is %2$s', 'libreform')
         ),
          esc_html($version_created_at),
          esc_html(WPLF_VERSION)
     ); ?>
      </p>

      <p>
      <?php echo esc_html(
          __('There might be new features available, would you like to update the form version?', 'libreform')
     ); ?>
      </p>

      <p>
        <label>
          <input type="checkbox" name="wplf_version_update_toggle" value="1">
          <?php echo esc_html(
              __('Yes, update when I save the form', 'libreform')
         ); ?>
        </label>
      </p>
    </div>
    <?php
    }
  }

  public function printDefaultForm() {
    $required = esc_html_x('(required)', 'libreform');
    $defaultName = esc_html_x('John Doe', 'Default placeholder name', 'libreform');
    $nameLabel = esc_html_x('Please enter your name', 'libreform');

    $defaultEmail = esc_html_x('example@email.com', 'Default placeholder email', 'libreform');
    $emailLabel = esc_html_x('Please enter your email address', 'libreform');

    $defaultMessage = esc_html_x('I wanted to ask about...', 'Default placeholder message', 'libreform');
    $messageLabel = esc_html_x('Write your message below', 'libreform');

    $buttonText = esc_html_x('Submit', 'libreform');
    $comment = esc_html_x('Any valid HTML form can be used here!', 'The HTML comment at the end of the example form', 'libreform');
    ?>

<div class="form-row">
  <label for="name">
    <strong><?=$nameLabel?></strong>
    <input type="text" name="name" id="name" placeholder="<?=$defaultName?>">
  </label>

  <label for="email">
    <strong><?=$emailLabel?> <?=$required?></strong>
    <input type="email" name="email" id="email" placeholder="<?=$defaultEmail?>" required>
  </label>
</div>

<div class="form-row">
  <label for="message">
    <strong><?=$messageLabel?></strong>
    <textarea name="message" rows="5" id="message" placeholder="<?=$defaultMessage ?>" required></textarea>
  </label>
</div>

<div class="form-row">
  <button type="submit"><?=$buttonText?></button>
</div>

<!-- <?=$comment?> --><?php
  }


  /**
   * Pre-populate form editor with default content
  */
  public function defaultContent($content) {
    global $pagenow;

    // only on post.php screen
    if ('post-new.php' !== $pagenow && 'post.php' !== $pagenow) {
      return $content;
    }

    // only for this cpt
    if (isset($_GET['post_type']) && self::$postType === $_GET['post_type']) {
      ob_start();
      $this->printDefaultForm();
      $content = esc_textarea(ob_get_clean());
    }

    return $content;
  }

  /**
   * Remove view action in edit.php for forms
  */
  public function removeRowActions($actions, $post) {
    if (self::$postType === $post->post_type) {
      unset($actions['view']);
    }

    return $actions;
  }

  /**
   * Custom columns in edit.php for Forms
  */
  public function customColumns($columns) {
    return [
      'cb' => $columns['cb'],
      'title' => $columns['title'],
      'shortcode' => __('Shortcode', 'libreform'),
      'submissions' => __('Submissions', 'libreform'),
      'date' => $columns['date'],
    ];
  }


  /**
   * Custom column display for Form CPT in edit.php
  */
  public function customColumnsContents($column, $post_id) {
    if ($column === 'shortcode') { ?>
      <input type="text" class="code" value='[libreform id="<?php echo intval($post_id); ?>"]' readonly><?php
    }

    if ($column === 'submissions') {
      // count number of submissions
      $submissions = get_posts([
        'post_type' => 'wplf-submission',
        'posts_per_page' => -1,
        'meta_key' => '_form_id',
        'meta_value' => $post_id,
        'suppress_filters' => false,
      ]); ?>

      <a href="<?php echo esc_url_raw(admin_url('edit.php?post_type=wplf-submission&form=' . $post_id)); ?>">
        <?php echo count($submissions); ?>
      </a><?php
    }
  }


  /**
   * Add meta box to show fields in form
  */
  public function addMetaBoxesCpt() {
    // Shortcode meta box
    add_meta_box(
        'wplf-shortcode',
        __('Shortcode', 'libreform'),
        array($this, 'metabox_shortcode'),
        self::$postType,
        'normal',
        'high'
   );

    // Dynamic values
    add_meta_box(
        'wplf-dynamic-values',
        __('Dynamic values', 'libreform'),
        array($this, 'metabox_dynamic_values'),
        self::$postType,
        'normal',
        'high'
   );

    // Messages meta box
    add_meta_box(
        'wplf-messages',
        __('Success Message', 'libreform'),
        array($this, 'metabox_thank_you'),
        self::$postType,
        'normal',
        'high'
   );

    // Media library meta box
    add_meta_box(
        'wplf-media',
        __('Files', 'libreform'),
        array($this, 'metabox_media_library'),
        self::$postType,
        'side'
   );

    // Form Fields meta box
    add_meta_box(
        'wplf-fields',
        __('Form Fields Detected', 'libreform'),
        array($this, 'metabox_form_fields'),
        self::$postType,
        'side'
   );

    // Email on submit
    add_meta_box(
        'wplf-submit-email',
        __('Emails', 'libreform'),
        array($this, 'metabox_submit_email'),
        self::$postType,
        'normal',
        'high'
   );

    // Submission title format meta box
    add_meta_box(
        'wplf-title-format',
        __('Submission Title Format', 'libreform'),
        array($this, 'meta_box_title_format'),
        self::$postType,
        'side'
   );
  }


  /**
   * Meta box callback for shortcode meta box
  */
  public function metabox_shortcode($post) {
?>
<p><input type="text" class="code" value='[libreform id="<?php echo esc_attr($post->ID); ?>"]' readonly></p>
<?php
  }

  /**
   * Meta box callback for dynamic values meta box
  */
  public function metabox_dynamic_values($post) {
    unset($post); ?>
    <select name="wplf-dynamic-values">
      <option default value=""><?php esc_html_e('Choose a dynamic value', 'libreform'); ?></option>

      <?php foreach ((WPLF_Dynamic_Values::get_available()) as $k => $v) {
        $key = sanitize_text_field($k);
        $labels = $v['labels'];
        $stringified = wp_json_encode($labels);

      // WPCS won't STFU. It's wrong. Again.
      echo "<option value='$key' data-labels='$stringified'>$labels[name]</option>"; // @codingStandardsIgnoreLine
      } ?>
    </select>

    <!-- Shown with JS. -->
    <div class="wplf-dynamic-values-help">
      <div class="description"></div>
      <div class="usage">
      <strong><?php esc_html_e('Usage', 'libreform'); ?>:&nbsp;</strong>
      <span></span>
      </div>
    </div>
<?php
  }

  /**
   * Meta box callback for fields meta box
  */
  public function metabox_thank_you($post) {
    // get post meta
    $meta = get_post_meta($post->ID);
    $message = isset($meta['_wplf_thank_you']) ?
    $meta['_wplf_thank_you'][0]
    : _x('Success!', 'Default success message', 'libreform');
?>
<p>
<?php wp_editor(esc_textarea($message), 'wplf_thank_you', array(
'wpautop' => true,
'media_buttons' => true,
'textarea_name' => 'wplf_thank_you',
'textarea_rows' => 6,
'teeny' => true,
)); ?>
</p>
<?php
  wp_nonce_field('wplf_form_meta', 'wplf_form_meta_nonce');
  }

  /**
   * Meta box callback for should files end up in media library
  */
  public function metabox_media_library($post) {
    $meta    = get_post_meta($post->ID);
    $checked = 'checked';

    if (isset($meta['_wplf_media_library']) && empty($meta['_wplf_media_library'][0])) {
        $checked = '';
    }

    echo "<input type='checkbox' " . esc_html($checked) . " name='wplf_media_library'>" ;
    ?>
    <label><?php esc_attr_e('Add files to media library', 'libreform'); ?></label>
    <?php
  }

  /**
   * Meta box callback for form fields meta box
  */
  public function metabox_form_fields() {
?>
<p><?php esc_html_e('Fields marked with * are required', 'libreform'); ?></p>
<div class="' . self::$postType . '-field-container">
<!--  <div class="' . self::$postType . '-field widget-top"><div class="widget-title"><h4>name</h4></div></div> -->
</div>
<input type="hidden" name="wplf_fields" id="wplf_fields">
<input type="hidden" name="wplf_required" id="wplf_required">
<?php
  }

  /**
   * Meta box callback for submit email meta box
  */
  public function metabox_submit_email($post) {
    $meta = get_post_meta($post->ID);
    $email_enabled = ! empty($meta['_wplf_email_copy_enabled']) ? (int) $meta['_wplf_email_copy_enabled'][0] : 0;
    $email_copy_to = isset($meta['_wplf_email_copy_to']) ? $meta['_wplf_email_copy_to'][0] : '';
    $email_copy_from = isset($meta['_wplf_email_copy_from']) ? $meta['_wplf_email_copy_from'][0] : '';
    $email_copy_from_address = isset($meta['_wplf_email_copy_from_address']) ? $meta['_wplf_email_copy_from_address'][0] : '';
    $email_copy_subject = isset($meta['_wplf_email_copy_subject']) ? $meta['_wplf_email_copy_subject'][0] : '';
    $email_copy_content = isset($meta['_wplf_email_copy_content']) ? $meta['_wplf_email_copy_content'][0] : '';

    $sitename = strtolower($_SERVER['SERVER_NAME']);
    if (substr($sitename, 0, 4) == 'www.') {
      $sitename = substr($sitename, 4);
    }
    $email_copy_from_default = 'wordpress@' . $sitename;
?>
<p>
<label for="wplf_email_copy_enabled">
  <input
    id="wplf_email_copy_enabled"
    name="wplf_email_copy_enabled"
    type="checkbox"
    <?php echo $email_enabled ? 'checked="checked"' : ''; ?>
  >
  <?php esc_html_e('Send an email copy when a form is submitted?', 'libreform'); ?>
</label>
</p>
<p class="wplf-email-copy-to-field">
<?php esc_attr_e('You may use any form field values and following global tags: submission-id, referrer, form-title, form-id, user-id, timestamp, datetime, language, all-form-data. All field values and tags should be enclosed in "%" markers.', 'libreform'); ?>
</p>
<p class="wplf-email-copy-to-field">
<label for="wplf_email_copy_to" style="display:inline-block;width:100px;font-weight:600;"><?php esc_attr_e('Send copy to', 'libreform'); ?></label>
<input
  type="text"
  name="wplf_email_copy_to"
  value="<?php echo esc_attr($email_copy_to); ?>"
  placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
  style="width:80%;"
>
</p>
<p class="wplf-email-copy-to-field">
<label for="wplf_email_copy_from" style="display:inline-block;width:100px;font-weight:600;"><?php esc_attr_e('Sender name', 'libreform'); ?></label>
<input
  type="text"
  name="wplf_email_copy_from"
  value="<?php echo esc_attr($email_copy_from); ?>"
  placeholder="WordPress"
  style="width:80%;"
>
</p>
<p class="wplf-email-copy-to-field">
<label for="wplf_email_copy_from_address" style="display:inline-block;width:100px;font-weight:600;"><?php esc_attr_e('Sender email', 'libreform'); ?></label>
<input
  type="text"
  name="wplf_email_copy_from_address"
  value="<?php echo esc_attr($email_copy_from_address); ?>"po
  placeholder="<?php echo esc_attr($email_copy_from_default); ?>"
  style="width:80%;"
>
</p>
<p class="wplf-email-copy-to-field">
<label for="wplf_email_copy_subject" style="display:inline-block;width:100px;font-weight:600;"><?php esc_attr_e('Subject', 'libreform'); ?></label>
<?php // @codingStandardsIgnoreStart ?>
<input
  type="text"
  name="wplf_email_copy_subject"
  value="<?php echo esc_attr($email_copy_subject); ?>"
  placeholder="<?php esc_attr_e('[%submission-id%] Submission from %referrer%', 'libreform'); ?>"
  style="width:80%;"
>
<?php // @codingStandardsIgnoreEnd ?>
</p>
<p class="wplf-email-copy-to-field" style="display:table;width:100%;">
<label for="wplf_email_copy_content" style="display:table-cell;width:105px;font-weight:600;vertical-align:top;"><?php esc_attr_e('Content', 'libreform'); ?></label>
<?php // @codingStandardsIgnoreStart ?>
<textarea
  name="wplf_email_copy_content"
  placeholder="<?php esc_attr_e('Form %form-title% (ID %form-id%) was submitted with values below', 'libreform'); ?>:

%all-form-data%"
  style="display:table-cell;width:94%;"
  rows="10"
><?php echo esc_attr($email_copy_content); ?></textarea>
<?php // @codingStandardsIgnoreEnd ?>
</p>
<?php
  }

  /**
   * Meta box callback for submission title format
  */
  public function meta_box_title_format($post) {
    // get post meta
    $meta = get_post_meta($post->ID);
    $default = '%form-title% #%submission-id%'; // default submission title format
    $format = isset($meta['_wplf_title_format']) ? $meta['_wplf_title_format'][0] : $default;
?>
<p><?php esc_html_e('Submissions from this form will use this formatting in their title.', 'libreform'); ?></p>
<p><?php esc_html_e('You may use any field values enclosed in "%" markers.', 'libreform'); ?></p>
<p>
<?php
  // translators: %submission-id% is not meant to be translated
  esc_html_e('In addition, you may use %submission-id%.', 'libreform');
?>
 </p>
<p>
<input
  type="text"
  name="wplf_title_format"
  value="<?php echo esc_attr($format); ?>"
  placeholder="<?php echo esc_attr($default); ?>"
  class="code"
  style="width:100%"
  autocomplete="off"
>
</p>
<?php
  }

  /**
   * Check and maybe load a static HTML template for a specific form.
 *
 * Hooked to `add_meta_boxes`.
 *
 * @param string $post_type Post type for which editor is being rendered for.
 * @param \WP_Post $post Current post object.
 *
 * @return void
  */
  public function maybeLoadImportedTemplate($post_type, $post) {
    if ($post_type !== self::$postType || $post->post_status === 'auto-draft') {
      return;
    }

    $form_id = (int) $post->ID;

    /**
   * Allows importing a static HTML template for a specific form ID.
   *
   * If the template returned is `null` then no template is loaded.
   *
   * @param string|null $template_content Raw HTML to import for a form.
   * @param int $form_id Form ID (WP_Post ID) to import template for.
   */
    $template_content = apply_filters('wplf_import_html_template', null, $form_id);

    if ($template_content === null) {
      return;
    }

    // Clear unwanted form tags. WPLF will insert those by itself when rendering a form.
    $template_content = preg_replace('%<form ?[^>]*?>%', '', $template_content);
    $template_content = preg_replace('%</form>%', '', $template_content);

    $this->override_form_template($template_content, $form_id);
  }

  /**
   * Override a form's template with an imported template file.
 *
 * @param string $template_content Raw HTML content to use for the form content.
 * @param int $form_id ID of form we're overriding the template for.
 *
 * @return void
  */
  protected function override_form_template($template_content, $form_id) {
    $this->maybe_persist_override_template($template_content, $form_id);

    static $times_content_replaced = 0;

    // Make the editor textarea uneditable.
    add_filter('the_editor', function ($editor) {
      if (! preg_match('%id="wp-content-editor-container"%', $editor)) {
        return $editor;
      }

      $editor = preg_replace('%\<textarea %', '<textarea readonly="readonly" ', $editor);

      $notice = _x(
          'This form template is being overridden by code, you must edit it in your project code',
          'Template override notice in form edit admin view',
          'libreform'
     );

      $notice = sprintf('<div class="wplf-template-override-notice">%s</div>', $notice);

      return $notice . $editor;
    });

    // Custom settings for the form editor.
    add_filter('wp_editor_settings', function ($settings, $editor_id) {
      if ($editor_id !== 'content') {
        return $settings;
      }

      $settings['tinymce'] = false;
      $settings['quicktags'] = false;
      $settings['media_buttons'] = false;

      return $settings;
    }, 10, 2);

    // Replace all editor content with template content.
    add_filter('the_editor_content', function ($content) use ($template_content, &$times_content_replaced) {
      // This is hacky, yes. We only want to override the content for the first
      // editor field we come by, meaning 99% of the time we hit the wanted form
      // template editor field at the top of the edit view page.
      if ($times_content_replaced > 0) {
        return $content;
      }

      $times_content_replaced++;

      return $template_content;
    });
  }

  /**
   * Check if we need to auto-persist the form template override into WP database.
   *
   * @param string $template Template to maybe persist.
   * @param int $form_id Form ID to persist template for.
   * @param bool $force Force a persist even though not required?
   *
   * @return void
   */
  protected function maybe_persist_override_template($template, $form_id, $force = false) {
    $templateHash = md5($template);
    $templateTransient = get_transient('wplf-template-override');

    if (!$templateTransient) {
      $templateTransient = [];
    }

    $notForcedAndHashNotChanged = (
      !$force &&
      (isset($templateTransient[$templateHash]) && $templateTransient[$templateHash] === $templateHash)
   );

    if ($notForcedAndHashNotChanged) {
      return;
    }

    // Safe-guard to prevent accidental infinite loops.
    remove_action('save_post', [$this, 'saveCpt']);

    $updated = wp_update_post([
      'ID' => (int) $form_id,
      'post_content' => $template,
    ]);

    add_action('save_post', [$this, 'saveCpt']);

    if ($updated) {
      $transient = array_merge($templateTransient, [$templateHash => date('U')]);

      set_transient('wplf-template-override', $transient, HOUR_IN_SECONDS * 8);
    }
  }

  public function deleteForm($post_id) {
    $post = get_post($post_id);

    if ($post->post_type !== self::$postType) {
      do_action("wplf_deleteForm", $post);
      do_action("wplf_{$post->slug}_deleteForm", $post);
      do_action("wplf_{$post->slug}_deleteForm", $post);

      $this->deleteTransients();
    }
  }

  /**
   * Handles saving our post meta
   */
  public function saveCpt($post_id) {
    // verify nonce
    if (! isset($_POST['wplf_form_meta_nonce'])) {
      return;
    } elseif (! wp_verify_nonce($_POST['wplf_form_meta_nonce'], 'wplf_form_meta')) {
      return;
    }

    // only for this cpt
    if (! isset($_POST['post_type']) || self::$postType !== $_POST['post_type']) {
      return;
    }


    if (is_multisite() && !current_user_can('unfiltered_html')) {
      wp_die(
          '<h1>' . esc_html__('You do not have unfiltered_html capability', 'libreform') . '</h1>' .
          '<p>' . esc_html__('Only Super Admins have unfiltered_html capability by default in WordPress Network.', 'libreform') . '</p>',
          403
     );
    }

    // check permissions.
    if (! current_user_can('edit_post', $post_id)) {
      return;
    }

    $this->deleteTransients();

    // save media checkbox
    if (isset($_POST['wplf_media_library'])) {
      update_post_meta($post_id, '_wplf_media_library', $_POST['wplf_media_library']);
    } else {
      update_post_meta($post_id, '_wplf_media_library', '');
    }

    // save success message
    if (isset($_POST['wplf_thank_you'])) {
      $success = wp_kses_post($_POST['wplf_thank_you']);
      $success = apply_filters('wplf_save_success_message', $success, $post_id);
      update_post_meta($post_id, '_wplf_thank_you', $success);
    }

    // save fields
    if (isset($_POST['wplf_fields'])) {
      update_post_meta($post_id, '_wplf_fields', sanitize_text_field($_POST['wplf_fields']));
    }

    // save required fields
    if (isset($_POST['wplf_required'])) {
      update_post_meta($post_id, '_wplf_required', sanitize_text_field($_POST['wplf_required']));
    }

    // save email copy enabled state
    if (isset($_POST['wplf_email_copy_enabled'])) {
      update_post_meta($post_id, '_wplf_email_copy_enabled', $_POST['wplf_email_copy_enabled'] === 'on');
    } else {
      update_post_meta($post_id, '_wplf_email_copy_enabled', 0);
    }

    // save email copy
    if (isset($_POST['wplf_email_copy_to'])) {
      $email_field = $_POST['wplf_email_copy_to'];
      $to = '';

      if (strpos($email_field, ',') > 0) {
        // Intentional. Makes no sense if the first character is a comma, so pass it along as a single address.
        // sanitize_email() should take care of the rest.
        $email_array = explode(',', $email_field);
        foreach ($email_array as $email) {
          $email = trim($email);
          $email = sanitize_email($email) . ', ';
          $to .= $email;
        }
        $to = rtrim($to, ', ');
      } else {
        $to = sanitize_email($email_field);
      }

      if (! empty($to)) {
        update_post_meta($post_id, '_wplf_email_copy_to', $to);
      } else {
        delete_post_meta($post_id, '_wplf_email_copy_to');
      }
    }

    // save email copy from
    if (isset($_POST['wplf_email_copy_from']) && ! empty($_POST['wplf_email_copy_from'])) {
      update_post_meta($post_id, '_wplf_email_copy_from', sanitize_text_field($_POST['wplf_email_copy_from']));
    } else {
      delete_post_meta($post_id, '_wplf_email_copy_from');
    }

    if (isset($_POST['wplf_email_copy_from_address']) && ! empty($_POST['wplf_email_copy_from_address'])) {
      update_post_meta($post_id, '_wplf_email_copy_from_address', sanitize_text_field($_POST['wplf_email_copy_from_address']));
    } else {
      delete_post_meta($post_id, '_wplf_email_copy_from_address');
    }

    // save email copy subject
    if (isset($_POST['wplf_email_copy_subject']) && ! empty($_POST['wplf_email_copy_subject'])) {
      update_post_meta($post_id, '_wplf_email_copy_subject', sanitize_text_field($_POST['wplf_email_copy_subject']));
    } else {
      delete_post_meta($post_id, '_wplf_email_copy_subject');
    }

    // save email copy content
    if (isset($_POST['wplf_email_copy_content']) && ! empty($_POST['wplf_email_copy_content'])) {
      update_post_meta($post_id, '_wplf_email_copy_content', wp_kses_post($_POST['wplf_email_copy_content']));
    } else {
      delete_post_meta($post_id, '_wplf_email_copy_content');
    }

    // save title format
    if (isset($_POST['wplf_title_format'])) {
      $safe_title_format = $_POST['wplf_title_format']; // TODO: are there any applicable sanitize functions?

      // A typical title format will include characters like <, >, %, -.
      // which means all sanitize_* fuctions will probably mess with the field
      // The only place the title formats are displayed are within value=""
      // attributes where of course they are escaped using esc_attr() so it
      // should be fine to save the meta field without further sanitisaton
      update_post_meta($post_id, '_wplf_title_format', $safe_title_format);
    }

    // save plugin version, update if allowed
    $updateAllowed = isset($_POST['wplf_update_plugin_version_to_meta']) && $_POST['wplf_update_plugin_version_to_meta'] === '1';

    if ($updateAllowed) {
      update_post_meta($post_id, '_wplf_plugin_version', WPLF_VERSION);
    }
  }


  /**
   * Strip <form> tags from the form content
 *
 * We apply <form> via the shortcode, you can't have nested forms anyway
  */
  public function stripFormTags($content) {
    return preg_replace('/<\/?form.*>/i', '', $content);
  }


  public function printForm(\WP_Post $form, $options = []) {
    $content = $options['content'] ?? null; // Override content in database
    $className = $options['className'] ?? null;
    $attributes = $options['attributes'] ?? [];

    if (!$content) {
      $content = $form->post_content;
    }

    $content = apply_filters('wplfBeforeRender', $content, $form, $options);

    $formContainsFileInputs = (
      strpos($content, "type='file'") !== false ||
      strpos($content, 'type="file"') !== false ||
      strpos($content, 'type=file') !== false
    );

    $id = intval($form->ID);

    // Filter null values out
    $attributes = array_filter([
      'data-form-id' => $id,
      'tabindex' => '-1',
      'class' => join(' ', array_filter(["libreform", "libreform-$id", $className])),
      // 'style' => 'visibility: hidden;',
      'enctype' => $formContainsFileInputs ? 'multipart/form-data' : null,
    ]);
    ?>
<form
  <?php
  // add custom attributes from shortcode to <form> element
  foreach ($attributes as $attr_name => $attr_value) {
    echo esc_attr($attr_name) . '="' . esc_attr($attr_value) . "\"\n";
  }
  ?>
><?php

  // This is where we output the user-input form html. We allow all HTML here. Yes, even scripts.
  echo $content;

  if (is_archive()) {
    global $wp;
    $current_url = home_url($wp->request);

    if (empty(get_option('permalink_structure'))) {
      $current_url = add_query_arg($wp->query_string, '', home_url($wp->request));
    }
    ?>
    <input type="hidden" name="referrer" value="<?php echo esc_attr($current_url); ?>">
    <input type="hidden" name="_referrer_id" value="archive">
    <input type="hidden" name="_referrer_archive_title" value="<?php echo esc_attr(get_the_archive_title()); ?>">
    <?php
  } else { ?>
    <input type="hidden" name="referrer" value="<?php the_permalink(); ?>">
    <input type="hidden" name="_referrer_id" value="<?php echo esc_attr(get_the_id()); ?>"><?php
  } ?>

  <input type="hidden" name="_form_id" value="<?=$id?>">
</form><?php
  }

  /**
   * The function we display the form with
  */
  public function render(\WP_Post $form, $options = []) {
    $preview = !empty($_GET['preview']) ? $_GET['preview'] : false;

    if ($form->post_status === 'publish' || $preview) {
      wp_enqueue_script('wplf-frontend');

      ob_start();
      $this->printForm($form, $options);
      $output = apply_filters('wplfAfterRender', ob_get_clean(), $form, $options);

      return $output;
    }

    return false;
  }

  public $settings = 'doge';

  /**
   * Shortcode for displaying a Form
   */
  public function shortcode($atts, $content = null) {
    $attributes = shortcode_atts(array(
    'id' => null,
    'classname' => '',
   ), $atts, 'libreform');

    // Allow disabling shortcode parsing in API requests.
    // This can't be done earlier, because the constant doesn't exist when add_shortcode is ran.
    $is_rest = defined('REST_REQUEST') ? true : false;
    $parse_shortcode_in_rest = $this->core->settings->get('parse-wplf-shortcode-rest-api');

    // Direct requests should contain it though.
    $is_wplf_endpoint = strpos($_SERVER['REQUEST_URI'], '/wp-json/wp/v2/' . self::$postType . '') !== false;

    // Because shortcode parsing can't actually be disabled, we output the "same" shortcode
    // instead of the form. This also normalizes the shortcodes.
    if ($is_rest && !$is_wplf_endpoint && !$parse_shortcode_in_rest) {
      $props = [];

      // If you change how the shortcode is rebuilt,
      // it's a breaking change and must be versioned accordingly.
      foreach ($attributes as $k => $v) {
        $props[] = "$k=\"$v\"";
      }

      return '[libreform ' . join($props, ' ') . ']';
    }

    // we don't render id and class as <form> attributes, unset them with array_diff_key
    $id = $attributes['id'];
    $className = $attributes['classname'];

    $attributes = array_diff_key($atts, array(
      'id' => null,
      'classname' => null,
    ));
log($attributes);
// die();
    foreach ($attributes as $k => $v) {
      if (is_numeric($k)) {
        unset($attributes[ $k ]);
        $attributes[ $v ] = null; // empty value
      }
    }

    // display form
    return $this->render(get_post($id), [
      'content' => $content,
      'className' => $className,
      'attributes' => $attributes,
    ]);
  }


  /**
   * Use the shortcode for previewing forms
  */
  public function replaceContentWithFormOnSingleForm($content) {
    $post = get_post();

    if (! isset($post->post_type) || $post->post_type !== self::$postType) {
      return $content;
    }

    // return $this->render($post);
    return '[libreform id="' . (int) $post->ID . '"]' . minifyHtml($content) . '[/libreform]';
  }

  /**
   * Set and show 404 page for visitors trying to see single form.
   */
  public function maybeFake404() {
    if (!is_singular(self::$postType)) {
      return;
    }

    $post = get_post();
    $allowDirect = $this->settings->get('allowDirect');
    $currentUserCanEditForm = current_user_can('edit_post', $post->ID);

    if (!$allowDirect && !$currentUserCanEditForm) {
      global $wp_query;
      $wp_query->set_404();
    }
  }

  /**
   * Delete all form related transients
   */
  public function deleteTransients() {
    delete_transient('wplf-template-override');
    // delete_transient('' . self::$postType . '-filter');
  }
}
