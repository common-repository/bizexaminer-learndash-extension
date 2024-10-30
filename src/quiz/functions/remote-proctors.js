import $ from "jquery";

import CONSTANTS from "../constants";
const { LD_FIELD_WRAPPER_SELECTOR, REMOTE_PROCTOR_FIELD_SELECTOR } = CONSTANTS;

/**
 * Handles showing/hiding remote proctor settings dependend on selected type
 */
export default ($section) => {
    const $remoteProctorSelect = $section.find(REMOTE_PROCTOR_FIELD_SELECTOR);
    const $remoteProctorSettings = $section.find("[data-bizexaminer-proctor]");

    $remoteProctorSettings
        .parents(LD_FIELD_WRAPPER_SELECTOR)
        .addClass("bizexaminer-remote-proctor-setting-field");

    const handleRemoteProctorChange = (value) => {
        $remoteProctorSettings.parents(LD_FIELD_WRAPPER_SELECTOR).hide();

        if (!value) {
            $remoteProctorSettings.parents(LD_FIELD_WRAPPER_SELECTOR).hide();
            return;
        }

        const valueParts = value.split("_-_");
        if (valueParts.length < 2) {
            $remoteProctorSettings
                .filter(`[data-bizexaminer-proctor]`)
                .parents(LD_FIELD_WRAPPER_SELECTOR)
                .hide();
            return;
        }
        const proctorType = valueParts[0];
        // TODO: better a11y for showing/hiding conditional fields?

        $remoteProctorSettings
            .filter(`[data-bizexaminer-proctor="${proctorType}"]`)
            .parents(LD_FIELD_WRAPPER_SELECTOR)
            .show();
    };

    $remoteProctorSelect.on("change", () => {
        handleRemoteProctorChange($remoteProctorSelect.val());
    });
    // init existing value
    handleRemoteProctorChange($remoteProctorSelect.val());
};
