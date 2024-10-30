import $ from "jquery";

import { getSelect2Args } from "../helper/select2";
import CONSTANTS from "../constants";
const {
    LD_FIELD_WRAPPER_SELECTOR,
    FIELD_ERROR_CLASS,
    ERROR_MESSAGE_SELECTOR,
    API_CREDENTIALS_FIELD_SELECTOR,
    EXAM_MODULE_FIELD_SELECTOR,
    REMOTE_PROCTOR_FIELD_SELECTOR,
} = CONSTANTS;

/**
 * Handles invalid API credentials and refetching api-dependend options on credentials change
 * TODO: better a11y for error messages / disabling fields?
 */
export default ($section) => {
    const $apiCredentialsField = $section.find(API_CREDENTIALS_FIELD_SELECTOR);
    const $apiCredentialsSelect = $section.find(
        "#learndash-quiz-bizexaminer-settings_bizExaminerApiCredentials"
    );

    const $examModuleSelect = $section.find(EXAM_MODULE_FIELD_SELECTOR);
    const $remoteProctorSelect = $section.find(REMOTE_PROCTOR_FIELD_SELECTOR);

    const $apiDependendSelects = $()
        .add($examModuleSelect)
        .add($remoteProctorSelect);

    /**
     * Handle Invalid API Credentials
     *
     * If a previous selected api credential set was deleted / is now invalid,
     * the field will have an error class
     * in this case inform the user, when the invalid value is selected
     * and remove the error if he selects another value
     * server will handle unsetting the now invalid value after the first save
     */
    const initialApiValue = $apiCredentialsSelect.val();
    const hadInitialApiValueError =
        $apiCredentialsField.hasClass(FIELD_ERROR_CLASS);

    /**
     * Handle Selecting New API Credentials
     * to fetch new bizExaminer Settings (exam modules and remote proctors)
     * and show error if no selected value
     */
    $apiCredentialsSelect.on("change", (e) => {
        const value = $apiCredentialsSelect.val();
        if (value === "" || value == "-1" || !value) {
            $apiCredentialsField.addClass(FIELD_ERROR_CLASS);
            $apiCredentialsField.find(ERROR_MESSAGE_SELECTOR).show();
            $examModuleSelect.prop("disabled", true);
            $remoteProctorSelect
                .val("-1")
                .trigger("change")
                .prop("disabled", true);
            return;
        } else if (!hadInitialApiValueError || value !== initialApiValue) {
            $apiCredentialsField.removeClass(FIELD_ERROR_CLASS);
            $apiCredentialsField.find(ERROR_MESSAGE_SELECTOR).hide();
        }

        $apiDependendSelects.each(function () {
            const $select = $(this);
            // set the data-attributes query data to include the new api credentials
            $select.data("select2-query-data", {
                ...($select.data("select2-query-data") || {}),
                api_credentials: value,
            });

            // reinit select 2 - LearnDash reads the query data on load, therefore it needs to be reinitialized
            // @see learndash-admin-settings-page.js lines #12-30
            $select.select2(getSelect2Args($select));
            // reset exam module error message after repopulating options
            $select.prop("disabled", false);

            const $field = $select.parents(LD_FIELD_WRAPPER_SELECTOR);
            $field.removeClass(FIELD_ERROR_CLASS);
            $field.find(ERROR_MESSAGE_SELECTOR).hide();
        });
    });

    if (
        !initialApiValue ||
        initialApiValue === "" ||
        initialApiValue == "-1" ||
        hadInitialApiValueError
    ) {
        $examModuleSelect.prop("disabled", true);
        $remoteProctorSelect.prop("disabled", true);
    }
};
