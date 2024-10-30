import $ from "jquery";
import initApiCredentials from "./functions/api-credentials";
import initExamModules from "./functions/exam-modules";
import initIncompatibleSettings from "./functions/incompatible-settings";
import initRemoteProctorSettings from "./functions/remote-proctors";
import initNestedSettings from "./functions/nested-settings";
import initSelect2 from "./functions/select2";

import "./styles.scss";

/**
 * Handles error showing/hiding, AJAX fetching of select options
 * and conditionally showing/hiding remote proctor settings
 */
$(() => {
    if (!window.bizExaminerQuizSettings) {
        return;
    }

    const $section = $(".learndash-quiz-bizexaminer-settings");

    /**
     * LearnDash only handles the error-message display for number-fields
     * handle for all fields in bizexaminer section
     */
    $section
        .find(".learndash-settings-field-error .learndash-section-field-error")
        .show();

    initApiCredentials($section);
    initExamModules($section);
    initRemoteProctorSettings($section);
    initNestedSettings($section);
    initIncompatibleSettings($section);
    initSelect2($section);
});
