import $ from "jquery";

import CONSTANTS from "../constants";
const {
    FIELD_ERROR_CLASS,
    API_CREDENTIALS_FIELD_SELECTOR,
    EXAM_MODULE_FIELD_SELECTOR,
    LD_FIELD_WRAPPER_SELECTOR,
    ERROR_MESSAGE_SELECTOR,
} = CONSTANTS;

export default ($section) => {
    const $examModuleSelect = $section.find(EXAM_MODULE_FIELD_SELECTOR);
    const $examModuleField = $examModuleSelect.parents(
        LD_FIELD_WRAPPER_SELECTOR
    );

    const $apiCredentialsField = $section.find(API_CREDENTIALS_FIELD_SELECTOR);
    const hadInitialApiValueError =
        $apiCredentialsField.hasClass(FIELD_ERROR_CLASS);

    const initialValue = $examModuleSelect.val();
    /**
     * Show error notice if no selected value
     */
    $examModuleSelect.on("change", (e) => {
        const value = $examModuleSelect.val();
        if (value === "" || value == "-1" || !value) {
            $examModuleField.addClass(FIELD_ERROR_CLASS);
            $examModuleField.find(ERROR_MESSAGE_SELECTOR).show();
        } else if (!hadInitialApiValueError || value !== initialValue) {
            $examModuleField.removeClass(FIELD_ERROR_CLASS);
            $examModuleField.find(ERROR_MESSAGE_SELECTOR).hide();
        }
    });

    /**
     * Workaround for bug #3 (LearnDash does not select selected value when using optgroups)
     * TODO: remove when LearnDash fixes the bug
     */
    const examModuleSelectData = $examModuleSelect.data("select2-query-data");
    if (!examModuleSelectData.be_selected_value) {
        return;
    }
    const selectedValue = examModuleSelectData.be_selected_value;
    const $selectedOption = $examModuleSelect.find(
        `option[value="${selectedValue}"]`
    );
    if ($selectedOption.length) {
        $selectedOption.prop("selected", true);
        $examModuleSelect.trigger("change");
    }
};
