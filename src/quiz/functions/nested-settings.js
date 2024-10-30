import $ from "jquery";

// LearnDash provides a parent_setting to only show settings if their parent is enabled.
// But it only works on 1 level. For some settings we need multiple levels, therefore handle that here.

export default ($section) => {
    $section.find("[data-biz-show-if]").each(function () {
        const $field = $(this);
        const $fieldWrapper = $field.parents(".sfwd_input");
        const parent = $field.data("biz-show-if");
        const $parent = $section.find(
            `[name="learndash-quiz-bizexaminer-settings[${parent}]"]`
        );
        if (!$parent.length) {
            return;
        }

        const updateValue = () => {
            if ($parent.is(":checked")) {
                $fieldWrapper.show();
            } else {
                if ($field.is("input")) {
                    if ($field.prop("type") === "checkbox") {
                        $field.prop("checked", false);
                    } else if ($field.prop("type") === "radio") {
                    } else {
                        $field.val("").trigger("change").trigger("click"); // learndash uses click handler to show/hide nested fields;
                    }
                } else if ($field.is("select")) {
                    $field.val("").trigger("change").trigger("click"); // learndash uses click handler to show/hide nested fields;
                }
                $fieldWrapper.hide();
            }
        };

        updateValue();
        $parent.on("change", updateValue);
    });
};
