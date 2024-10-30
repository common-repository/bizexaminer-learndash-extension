<?php

// phpcs:disable Generic.Files.LineLength.TooLong

use BizExaminer\LearnDashExtension\Helper\Util;

defined('ABSPATH') || exit;
?>

<h3><?php esc_html_e('API Credentials', 'bizexaminer-learndash-extension'); ?></h3>
<p>
    <?php echo esc_html(sprintf(
        /* translators: placeholder: quiz label */
        __(
            'You can add multiple API credentials and choose which one to use for each %s you set up.',
            'bizexaminer-learndash-extension'
        ),
        learndash_get_custom_label_lower('quiz')
    )); ?>
</p>

<table class="learndash-settings-table learndash-settings-table-bizexaminer-api-credentials widefat striped" cellspacing="0">
    <thead>
        <tr>
            <th class="col-index" id="ColIndex">
                <span class="screen-reader-text">
                    <?php echo esc_html_x('Index', 'API Credentials table numbering header', 'bizexaminer-learndash-extension'); ?>
                </span>
            </th>
            <th class="col-name">
                <div class="col-header-wrapper">
                    <?php /* set id on span so not the complete help text is used for aria-labelledby */ ?>
                    <span id="ColHeaderName">
                        <?php echo esc_html_x('Name', 'API credentials set name label', 'bizexaminer-learndash-extension'); ?>
                    </span>
                    <?php /* help text output taken from LearnDash_Settings_Fields::show_section_field_row */ ?>
                    <a class="sfwd_help_text_link" style="cursor:pointer;" title="<?php esc_html_e('Click for Help!', 'learndash'); ?>" onclick="toggleVisibility('ColHeaderName_tip');">
                        <img alt="" src="<?php echo esc_url(LEARNDASH_LMS_PLUGIN_URL); ?>assets/images/question.png" />
                    </a>
                    <div id="ColHeaderName_tip" class="sfwd_help_text_div" style="display: none;">
                        <label class="sfwd_help_text">
                            <?php esc_html_e(
                                'The name of this credentials set (only used internally for better organisation).',
                                'bizexaminer-learndash-extension'
                            ); ?>
                        </label>
                    </div>
                </div>
            </th>
            <th class="col-api_key_instance">
                <div class="col-header-wrapper">
                    <?php /* set id on span so not the complete help text is used for aria-labelledby */ ?>
                    <span id="ColHeaderInstance">
                        <?php echo esc_html_x('Instance Domain', 'API credentials set instance domain label', 'bizexaminer-learndash-extension'); ?>
                    </span>
                    <?php /* help text output taken from LearnDash_Settings_Fields::show_section_field_row */ ?>
                    <a class="sfwd_help_text_link" style="cursor:pointer;" title="<?php esc_html_e('Click for Help!', 'learndash'); ?>" onclick="toggleVisibility('ColHeaderInstance_tip');">
                        <img alt="" src="<?php echo esc_url(LEARNDASH_LMS_PLUGIN_URL); ?>assets/images/question.png" />
                    </a>
                    <div id="ColHeaderInstance_tip" class="sfwd_help_text_div" style="display: none;">
                        <label class="sfwd_help_text">
                            <?php esc_html_e(
                                'The domain name of your bizExaminer instance (without https:// or path).',
                                'bizexaminer-learndash-extension'
                            ); ?>
                        </label>
                    </div>
                </div>
            </th>
            <th class="col-api_key_owner">
                <div class="col-header-wrapper">
                    <?php /* set id on span so not the complete help text is used for aria-labelledby */ ?>
                    <span id="ColHeaderAPIKeyOwner">
                        <?php echo esc_html_x('API Key Owner', 'API key owner label', 'bizexaminer-learndash-extension'); ?>
                    </span>
                    <?php /* help text output taken from LearnDash_Settings_Fields::show_section_field_row */ ?>
                    <a class="sfwd_help_text_link" style="cursor:pointer;" title="<?php esc_html_e('Click for Help!', 'learndash'); ?>" onclick="toggleVisibility('ColHeaderAPIKeyOwner_tip');">
                        <img alt="" src="<?php echo esc_url(LEARNDASH_LMS_PLUGIN_URL); ?>assets/images/question.png" />
                    </a>
                    <div id="ColHeaderAPIKeyOwner_tip" class="sfwd_help_text_div" style="display: none;">
                        <label class="sfwd_help_text">
                            <?php esc_html_e(
                                'The API key for the (content) owner.',
                                'bizexaminer-learndash-extension'
                            ); ?>
                        </label>
                    </div>
                </div>
            </th>
            <th class="col-api_key_organisation">
                <div class="col-header-wrapper">
                    <?php /* set id on span so not the complete help text is used for aria-labelledby */ ?>
                    <span id="ColHeaderAPIKeyOrganisation">
                        <?php echo esc_html_x(
                            'API Key Organisation',
                            'API key organisation label',
                            'bizexaminer-learndash-extension'
                        ); ?>
                    </span>
                    <?php /* help text output taken from LearnDash_Settings_Fields::show_section_field_row */ ?>
                    <a class="sfwd_help_text_link" style="cursor:pointer;" title="<?php esc_html_e('Click for Help!', 'learndash'); ?>" onclick="toggleVisibility('ColHeaderAPIKeyOrganisation_tip');">
                        <img alt="" src="<?php echo esc_url(LEARNDASH_LMS_PLUGIN_URL); ?>assets/images/question.png" />
                    </a>
                    <div id="ColHeaderAPIKeyOrganisation_tip" class="sfwd_help_text_div" style="display: none;">
                        <label class="sfwd_help_text">
                            <?php esc_html_e(
                                'The API key for the organisation.',
                                'bizexaminer-learndash-extension'
                            ); ?>
                        </label>
                    </div>
                </div>
            </th>
            <th class="col-infos">
                <div class="col-header-wrapper">
                    <span id="ColHeaderInfos"><?php echo esc_html_x('Infos', 'API key infos', 'bizexaminer-learndash-extension'); ?></span>
                </div>
            </th>
            <th class="col-actions">
                <div class="col-header-wrapper">
                    <?php /* set id on span so not the complete help text is used for aria-labelledby */ ?>
                    <span id="ColHeaderActions">
                        <?php echo esc_html_x('Actions', 'API key actions label', 'bizexaminer-learndash-extension'); ?>
                    </span>
                    <?php /* help text output taken from LearnDash_Settings_Fields::show_section_field_row */ ?>
                    <a class="sfwd_help_text_link" style="cursor:pointer;" title="<?php esc_html_e('Click for Help!', 'learndash'); ?>" onclick="toggleVisibility('ColHeaderActions_tip');">
                        <img alt="" src="<?php echo esc_url(LEARNDASH_LMS_PLUGIN_URL); ?>assets/images/question.png" />
                    </a>
                    <div id="ColHeaderActions_tip" class="sfwd_help_text_div" style="display: none;">
                        <label class="sfwd_help_text">
                            <?php esc_html_e(
                                'Actions for this API credentials set.',
                                'bizexaminer-learndash-extension'
                            ); ?>
                        </label>
                    </div>
                </div>
            </th>
        </tr>
    </thead>
    <tbody>
        <?php
        $i = 1;
        foreach ($data['values'] as $setId => $credentialsSet) {
            $oldI = $i; // keep for counting, allthough 'new' should always be the last item
            if ($setId === 'new') {
                $i = 'new';
            }
            /**
             * same as in load_settings_fields for building the field name
             * see note above about wp_settings_fields
             */
            $fieldPrefix = "{$data['field_prefix']}[{$setId}]";
            $rowId = "bizexaminer-api-credentials-{$setId}";

            $hasError = !empty($data['errors']["bizexaminer-invalid-api-credentials-{$setId}"]) &&
                $data['errors']["bizexaminer-invalid-api-credentials-{$setId}"]['type'] === 'error';
            $hasSuccess = !empty($data['errors']["bizexaminer-invalid-api-credentials-{$setId}"]) &&
                $data['errors']["bizexaminer-invalid-api-credentials-{$setId}"]['type'] === 'success';
            $error = $hasError ? $data['errors']["bizexaminer-invalid-api-credentials-{$setId}"]['message'] : null;
            $success = $hasSuccess ? $data['errors']["bizexaminer-invalid-api-credentials-{$setId}"]['message'] : null;

            $errorClass = $hasError ? 'has-error' : ($hasSuccess ? 'has-success' : '');
            ?>
            <tr id="<?php echo esc_attr($rowId); ?>" class="js-bizexaminer-api-item bizexaminer-api-row <?php echo esc_attr($errorClass); ?>">
                <th scope="row" id="<?php echo esc_attr("{$rowId}-header"); ?>" class="col-index">
                    <div class="sfwd_option_div">
                        <span class="screen-reader-text">
                            <?php
                            /* translators: followed by the row number */
                            echo esc_html_x(
                                'API Credentials ',
                                'api credentials set row screen reader label',
                                'bizexaminer-learndash-extension'
                            ); ?>
                        </span>
                        <?php
                        if ($i === 'new') {
                            echo esc_html_x('New', 'new api credentials set label', 'bizexaminer-learndash-extension');
                        } else {
                            echo esc_html($i);
                        } ?>
                    </div>
                </th>
                <td class="col-name col-valign-middle">
                    <div class="sfwd_option_div">
                        <?php
                        if (isset($data['fields']["{$fieldPrefix}[api_key_name]"])) {
                            $data['fields']["{$fieldPrefix}[api_key_name]"]['args']['attrs']['aria-labelledby'] = "{$rowId}-header ColHeaderName";
                            call_user_func(
                                $data['fields']["{$fieldPrefix}[api_key_name]"]['args']['display_callback'],
                                $data['fields']["{$fieldPrefix}[api_key_name]"]['args']
                            );
                        }
                        ?>
                        <?php if ($error) { ?>
                            <span class="error"><?php echo esc_html($error); ?></span>
                        <?php } ?>
                        <?php if ($success) { ?>
                            <span class="success"><?php echo esc_html($success); ?></span>
                        <?php } ?>
                    </div>
                </td>
                <td class="col-api_key_instance col-valign-middle">
                    <div class="sfwd_option_div">
                        <?php
                        if (isset($data['fields']["{$fieldPrefix}[api_key_instance]"])) {
                            $data['fields']["{$fieldPrefix}[api_key_instance]"]['args']['attrs']['aria-labelledby'] = "{$rowId}-header ColHeaderInstance";
                            call_user_func(
                                $data['fields']["{$fieldPrefix}[api_key_instance]"]['args']['display_callback'],
                                $data['fields']["{$fieldPrefix}[api_key_instance]"]['args']
                            );
                        }
                        ?>
                    </div>
                </td>
                <td class="col-api_key_owner col-valign-middle">
                    <div class="sfwd_option_div">
                        <?php
                        if (isset($data['fields']["{$fieldPrefix}[api_key_owner]"])) {
                            $data['fields']["{$fieldPrefix}[api_key_owner]"]['args']['attrs']['aria-labelledby'] = "{$rowId}-header ColHeaderAPIKeyOwner";
                            call_user_func(
                                $data['fields']["{$fieldPrefix}[api_key_owner]"]['args']['display_callback'],
                                $data['fields']["{$fieldPrefix}[api_key_owner]"]['args']
                            );
                        }
                        ?>
                    </div>
                </td>
                <td class="col-api_key_organisation col-valign-middle">
                    <div class="sfwd_option_div">
                        <?php
                        if (isset($data['fields']["{$fieldPrefix}[api_key_organisation]"])) {
                            $data['fields']["{$fieldPrefix}[api_key_organisation]"]['args']['attrs']['aria-labelledby'] = "{$rowId}-header ColHeaderAPIKeyOrganisation";
                            call_user_func(
                                $data['fields']["{$fieldPrefix}[api_key_organisation]"]['args']['display_callback'],
                                $data['fields']["{$fieldPrefix}[api_key_organisation]"]['args']
                            );
                        }
                        ?>
                    </div>
                </td>
                <th class="col-infos col-valign-middle">
                    <div class="sfwd_option_div">
                        <?php
                        $uses = $data['usesPerCredentialSet'][$setId] ?? 0;
                        ?>
                        <span class="col-infos__uses">
                            <?php echo esc_html(sprintf(
                                /* translators: %1$s number of uses, %2$s quizzes post type label */
                                _x(
                                    'Used in %1$s %2$s',
                                    'api credentials usage info',
                                    'bizexaminer-learndash-extension'
                                ),
                                $uses,
                                $uses > 1 ?
                                    \LearnDash_Custom_Label::get_label('quizzes') :
                                    \LearnDash_Custom_Label::get_label('quiz')
                            )); ?>
                        </span>
                    </div>
                </th>
                <td class="col-actions js-bizexaminer-api-actions infos col-valign-middle">
                    <div class="sfwd_option_div bizexaminer-actions-actions">
                        <?php
                        if (isset($data['fields']["{$fieldPrefix}[actions]"])) {
                            $actionField = $data['fields']["{$fieldPrefix}[actions]"]['args'];
                            $data['fields']["{$fieldPrefix}[actions]"]['args']['label'] = sprintf(
                                /* translators: %d is the row id (int or 'new') */
                                esc_html_x(
                                    'Actions for API Credentials Set %s',
                                    'api credentials actions group label',
                                    'bizexaminer-learndash-extension'
                                ),
                                $i
                            );


                            foreach ($actionField['options'] as $key => $actionArgs) {
                                printf(
                                    '<button
                                        class="button button-secondary js-bizexaminer-action-button"
                                        data-action="%1$s"
                                        aria-label="%2$s"
                                        %3$s>
                                        %4$s
                                    </button>',
                                    esc_attr($key),
                                    esc_attr(sprintf($actionArgs['button_aria-label'], $i)),
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in htmlAttrs
                                    Util::htmlAttrs($actionArgs['attrs'] ?? []),
                                    esc_html($actionArgs['button_label'])
                                );
                            }
                            call_user_func(
                                $data['fields']["{$fieldPrefix}[actions]"]['args']['display_callback'],
                                $data['fields']["{$fieldPrefix}[actions]"]['args']
                            );
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <?php
            if ($i = 'new') {
                $i = $oldI;
            }
            $i++;
        }
        ?>
    </tbody>
</table>

<div>
    <h4><?php esc_html_e('How to get your API credentials', 'bizexaminer-learndash-extension'); ?></h4>
    <p>
        <?php esc_html_e(
            'Log into your bizExaminer instance as administrator and go to "Settings" >
                "Owner" / "Organisation" to copy your API credentials.',
            'bizexaminer-learndash-extension'
        ); ?>
    </p>
    <p>
        <?php esc_html_e(
            'If you can not find these settings or do not have access to an administrator account,
                please contact the bizExaminer support.',
            'bizexaminer-learndash-extension'
        ); ?>
    </p>
</div>

<?php
// phpcs: enable