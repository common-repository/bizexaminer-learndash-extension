import CONSTANTS from "../constants";
const {
    LD_FIELD_WRAPPER_SELECTOR,
    FIELD_ERROR_CLASS,
    ERROR_MESSAGE_SELECTOR,
    ERROR_MESSAGE_CLASS,
} = CONSTANTS;

/**
 * Gets the ajax args for select2 initialization
 * based off the learndash defaults, plus additional custom error handling
 *
 * @see learndash-admin-settings-page.js lines #12-30
 *
 * @param {jQuery} $select
 * @returns
 */
const getSelect2AjaxArgs = ($select) => {
    const learndashAjaxSettings = learndash_settings_select2_ajax(
        $select.get(0)
    );
    return {
        ...learndashAjaxSettings,
        beforeSend: (jqXHR, settings) => {
            $select
                .parents(LD_FIELD_WRAPPER_SELECTOR)
                .removeClass(FIELD_ERROR_CLASS)
                .find(ERROR_MESSAGE_SELECTOR)
                .remove();
            if (learndashAjaxSettings.beforeSend) {
                learndashAjaxSettings.beforeSend();
            }
        },
        error: (jqXHR, status, error) => {
            if (
                jqXHR.responseJSON &&
                jqXHR.responseJSON.data &&
                jqXHR.responseJSON.data.error
            ) {
                const errorMessage = jqXHR.responseJSON.data.error;
                $select
                    .parents(LD_FIELD_WRAPPER_SELECTOR)
                    .addClass(FIELD_ERROR_CLASS)
                    .find(".sfwd_option_div")
                    .append(
                        `<div class="${ERROR_MESSAGE_CLASS}">${errorMessage}</div>`
                    );
                $select.select2("close");
            }

            if (learndashAjaxSettings.error) {
                learndashAjaxSettings.error();
            }
        },
    };
};

/**
 * Gets the default learndash select2 args
 * for when a select2 has to be reinitialized
 *
 * @see learndash-admin-settings-page.js lines #12-30
 *
 * @param {jQuery} $select
 * @returns {object}
 */
const getSelect2Args = ($select) => {
    let placeholder = $select.attr("placeholder");
    if (typeof placeholder === "undefined" || placeholder === "") {
        placeholder = $select.find("option[value='']").text();
    }
    if (typeof placeholder === "undefined" || placeholder === "") {
        placeholder = $select.find("option[value='-1']").text();
    }
    if (typeof placeholder === "undefined" || placeholder === "") {
        placeholder = "Select an option";
    }

    return {
        ...learndash_get_base_select2_args(),
        ajax: getSelect2AjaxArgs($select),
        placeholder: placeholder,
    };
};

export { getSelect2Args, getSelect2AjaxArgs };
