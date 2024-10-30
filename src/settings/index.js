import $ from "jquery";
import { __ } from "@wordpress/i18n";

import "./styles.scss";

$(() => {
    const $section = $("#settings_bizexaminer_api_credentials");
    const $fieldsWrapper = $section.find(
        ".settings_bizexaminer_api_credentials"
    );
    const $table = $fieldsWrapper.find(
        ".learndash-settings-table-bizexaminer-api-credentials"
    );

    const $items = $table.find(".js-bizexaminer-api-item");
    const $newItem = $items.filter("#bizexaminer-api-credentials-new");
    const $newItemActions = $newItem.find(".js-bizexaminer-api-actions");

    const $buttonAdd = $(
        `<button class="button button-secondary bizexaminer-button-add">${__(
            "Add API-Credentials",
            "bizexaminer-learndash-extension"
        )}</button>`
    );

    /**
     * Show / Hide the new item row
     */
    const toggleNewItem = (show) => {
        $newItem.toggle(show);
        $newItem
            .find(
                ".js-bizexaminer-api-name, .js-bizexaminer-api-instance, .js-bizexaminer-api-owner, .js-bizexaminer-api-organisation"
            )
            .prop("required", show); // set or remove required attribute

        if (!show) {
            $newItem
                .find(
                    ".js-bizexaminer-api-instance, .js-bizexaminer-api-owner, .js-bizexaminer-api-organisation"
                )
                .val("");
        }
    };

    const handleAddButtonClick = (e) => {
        e.preventDefault(); // prevent form submitting
        toggleNewItem(true);
        $buttonAdd.hide();
    };

    $buttonAdd.insertAfter($table);
    $buttonAdd.on("click", handleAddButtonClick);

    /**
     * handle hiding and resetting the new-row
     */
    const $buttonRemoveNew = $(
        `<button class="button button-secondary" aria-label="${__(
            "Remove new credentials",
            "bizexaminer-learndash-extension"
        )}">${__("Remove", "bizexaminer-learndash-extension")}</button>`
    );

    const handleRemoveNewButtonClick = (e) => {
        e.preventDefault(); // prevent form submitting
        toggleNewItem(false);
        $buttonAdd.show();
    };

    $buttonRemoveNew.appendTo($newItemActions);
    $buttonRemoveNew.on("click", handleRemoveNewButtonClick);

    /**
     * hide/show new item on load
     */
    if ($items.length > 1) {
        // more than one (one is always the new-placeholder)
        toggleNewItem(false);
        $buttonAdd.show();
    } else {
        // only one item - that's the "new" item - init / show it
        toggleNewItem(true);
        //do not show button
        $buttonAdd.hide();
        $buttonRemoveNew.hide();
    }

    /**
     * Disallow selecting delete AND test
     * doesn't use radiobox because it does not allow unselecting the value
     */
    $items.each(function () {
        const $itemRow = $(this);
        const $actions = $itemRow.find(".js-bizexaminer-api-actions");
        const $delete = $actions.find(`input[value="delete"`);
        const $test = $actions.find(`input[value="test"`);

        $delete.on("change", () => {
            if ($delete.prop("checked")) {
                $test.prop("checked", false);
            }
        });

        $test.on("change", () => {
            if ($test.prop("checked")) {
                $delete.prop("checked", false);
            }
        });
    });

    /**
     * Handle Action buttons
     */
    $table.find(".js-bizexaminer-api-actions").each(function () {
        const $colRow = $(this);
        const $buttons = $colRow.find(".js-bizexaminer-action-button");
        $buttons.on("click", function (e) {
            e.preventDefault();
            const action = $(this).data("action");
            $colRow
                .find(`input[type="checkbox"][value="${action}"]`)
                .prop("checked", true);
            /**
             * the default submit button has an id "submit"
             * which will prevent .trigger("submit") from working
             */
            $(this).parents("form").find("#submit").trigger("click");
        });
        $colRow
            .find(".bizexaminer-actions-actions")
            .addClass("buttons-enabled");
    });
});
