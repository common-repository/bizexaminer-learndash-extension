import $ from "jquery";

import CONSTANTS from "../constants";
const { ENABLED_FIELD_SELECTOR } = CONSTANTS;

let $fields = $(); // store for easier retrieval on saving

/**
 * Helper function to handle the disabling of one field
 *
 * @param {bool} disable
 * @param {string} groupKey
 * @param {string} fieldKey
 * @param {object} fieldSettings
 * @param {bool} resetValue When disable is false, whether to resetValues to initial state
 */
const toggleField = (
    disable,
    groupKey,
    fieldKey,
    fieldSettings,
    resetValue = true
) => {
    const $field = $(`[name="learndash-quiz-${groupKey}[${fieldKey}]"]`);
    if (!$field.length) {
        return;
    }
    $fields = $fields.add($field); // store all fields for easier later retrieval

    const $fieldWrapper = $field.parents(".sfwd_input");

    // add error/disabled class
    $fieldWrapper.toggleClass("bizexaminer-incompatible", disable);

    // disable input
    $field.prop("disabled", disable).addClass("bizexaminer-disabled");

    if (disable) {
        // reset value depending on type (checkbox, select)
        switch (fieldSettings.type) {
            case "checkbox":
                $field
                    .prop("checked", fieldSettings.reset_value === "on")
                    .trigger("change")
                    .trigger("click"); // learndash uses click handler
                break;
            case "select":
                if (
                    !$field.find(`option[value="${fieldSettings.reset_value}"]`)
                        .length
                ) {
                    $field.append(
                        $(
                            `<option value="${fieldSettings.reset_value}">${
                                fieldSettings.reset_value_label ||
                                fieldSettings.reset_value
                            }</option>`
                        )
                    );
                }
                $field
                    .val(fieldSettings.reset_value)
                    .trigger("change")
                    .trigger("click"); // learndash uses click handler to show/hide nested fields;
                break;
            case "radio":
                $field
                    .filter(`[value="${fieldSettings.reset_value}"]`)
                    .prop("checked", true)
                    .trigger("click"); // learndash uses click handler to show/hide nested fields;
                break;
            default:
                $field.val(fieldSettings.reset_value);
                break;
        }

        // add error message
        const $errorMessageWrapper = $fieldWrapper
            .find(".sfwd_option_input")
            .first(); // prevent multiple messages in nested/conditional options
        if (
            !$errorMessageWrapper.find(".bizexaminer-incompatible-message")
                .length
        ) {
            $errorMessageWrapper.append(
                `<p class="bizexaminer-incompatible-message">${fieldSettings.help_text}</p>`
            );
        }
    } else {
        $fieldWrapper.find(".bizexaminer-incompatible-message").remove();
        switch (fieldSettings.type) {
            case "select":
                if (resetValue) {
                    $field.val(0); // select default option
                }
                $field.trigger("change").trigger("click"); // learndash uses click handler to show/hide nested fields;
                break;
        }
    }

    /**
     * toggling of nested fields by learndash is somehow buggy for some field types (eg selects)
     * therefore disable them specifically
     * do not add message here again
     */
    const $nestedFields = $(
        `.ld-settings-sub[data-parent-field="learndash-quiz-${groupKey}_${fieldKey}_field"]`
    );
    $nestedFields.toggleClass("bizexaminer-incompatible", disable);
    $nestedFields
        .find("input,select,textarea,button")
        .prop("disabled", disable);
};

/**
 * On save: turn all disabled fields into readonly so they are sent to server
 *  then turn them back to disabled again
 *
 * Handling of listening to save taken from wpProQuiz_admin.js
 */
const initSaveDisabledFieldsHandling = () => {
    const enableFieldsForSave = () => {
        $fields
            .filter(":disabled")
            .addClass("bizexaminer-saving")
            .prop("disabled", false)
            .prop("readonly", true);
    };

    const disableFieldsAfterSafe = () => {
        $fields
            .filter(".bizexaminer-saving")
            .removeClass("bizexaminer-saving")
            .prop("disabled", true)
            .prop("readonly", false);
    };

    let wasSaving = false;
    let hasChangedInputs = false;
    let savingTimeout = null;
    // Classic editor
    if (!window.wp.blocks) {
        const handleClassicEditorSaving = () => {
            if (savingTimeout) {
                clearTimeout(savingTimeout);
            }
            enableFieldsForSave();
            savingTimeout = setTimeout(disableFieldsAfterSafe, 3000); // 3seconds to save
        };
        $("#wpProQuiz_save,input[name=save]").on(
            "click",
            handleClassicEditorSaving
        );
        $("#publish").on("click", handleClassicEditorSaving);
        $("#save-post").on("click", handleClassicEditorSaving);
    } else {
        // Gutenberg/Block Editor
        // @see https://github.com/WordPress/gutenberg/issues/17632
        window.wp.data.subscribe(() => {
            const isSavingMetaBoxes = window.wp.data
                .select("core/edit-post")
                .isSavingMetaBoxes();

            const isDoneSaving = wasSaving && !isSavingMetaBoxes;

            if (isSavingMetaBoxes && !hasChangedInputs) {
                enableFieldsForSave();
                hasChangedInputs = true;
            }
            if (isDoneSaving) {
                hasChangedInputs = false;
                disableFieldsAfterSafe();
            }

            wasSaving = isSavingMetaBoxes;
        });
    }
};

const initIncompatibleSettingsHandling = ($section) => {
    const { incompatibleSettings, builderTabHelpText } =
        window.bizExaminerQuizSettings;

    // reset all conditionals if bizExaminer is disabled
    const $enabledInput = $section.find(ENABLED_FIELD_SELECTOR);

    // handle conditional incompatible settings
    const handleConditionalFieldValueChange = (
        $field,
        fieldSettings,
        resetValue
    ) => {
        const { type, value, fieldGroups } = fieldSettings;

        let matchesValue = false;
        switch (type) {
            case "checkbox":
                matchesValue =
                    (value === "on" && $field.prop("checked")) ||
                    (value === "off" && !$field.prop("checked"));
                break;
        }

        // toggle settings
        Object.keys(fieldGroups).forEach((groupKey) => {
            const groupSettings = fieldGroups[groupKey];
            Object.keys(groupSettings).forEach((fieldKey) => {
                const fieldSettings = groupSettings[fieldKey];
                toggleField(
                    matchesValue,
                    groupKey,
                    fieldKey,
                    fieldSettings,
                    resetValue
                );
            });
        });
    };

    Object.keys(incompatibleSettings).forEach((conditionalFieldKey) => {
        const $field = $(
            `input[name="learndash-quiz-bizexaminer-settings[${conditionalFieldKey}]"]`
        );
        // handle change of value
        $field.on("change", function () {
            handleConditionalFieldValueChange(
                $(this),
                incompatibleSettings[conditionalFieldKey],
                true
            );
        });

        // When bizExaminer enabled/disabled changes, refresh enabled/disabled states
        $enabledInput.on("change", function () {
            handleConditionalFieldValueChange(
                $field,
                incompatibleSettings[conditionalFieldKey],
                false // do not reset values, because we wan't to keep values for not-disabled fields if the user set one
            );
        });

        // init with pre-selected value from server
        handleConditionalFieldValueChange(
            $field,
            incompatibleSettings[conditionalFieldKey],
            false // do not reset values, because we wan't to keep values for not-disabled fields if the user set one
        );
    });

    const handleEnabledChange = () => {
        const bizExaminerEnabled = !!$enabledInput.prop("checked");
        const disableBuilder = bizExaminerEnabled;
        // disable builder
        $("#tab-learndash_quiz_builder")
            .prop("disabled", disableBuilder)
            .attr("title", disableBuilder ? builderTabHelpText : "");
        if (bizExaminerEnabled && disableBuilder) {
            $("#tab-sfwd-quiz-settings").trigger("click"); // if page loads with builder tab open
        }
    };

    $enabledInput.on("change", handleEnabledChange);
    handleEnabledChange(); // init with pre-selected value from server
};

export default ($section) => {
    if (!window.bizExaminerQuizSettings.incompatibleSettings) {
        return;
    }

    initIncompatibleSettingsHandling($section);
    initSaveDisabledFieldsHandling();
};
