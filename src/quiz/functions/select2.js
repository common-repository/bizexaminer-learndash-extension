import $ from "jquery";
import { getSelect2Args } from "../helper/select2";

export default ($section) => {
    $section
        .find(`select[data-ld-select2="1"][data-select2-query-data]`)
        .each(function () {
            const $select = $(this);
            // reinit all bizExaminer select2 with custom select2 args
            $select.select2(getSelect2Args($select));
        });
};
