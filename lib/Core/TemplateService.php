<?php

namespace BizExaminer\LearnDashExtension\Core;

use BizExaminer\LearnDashExtension\Internal\Interfaces\EventManagerAwareInterface;
use BizExaminer\LearnDashExtension\Internal\Traits\EventManagerAwareTrait;

/**
 * A service to render templates which allows overwriting templates in themes and filtering of output
 */
class TemplateService implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * The main template directory in which to search for templates (eg plugin/templates)
     *
     * @var string
     */
    protected string $templateDir;

    /**
     * Paths to search for overwritten templates (eg in themes)
     *
     * @var array
     */
    protected array $templatePaths;

    /**
     * Creates a new TemplateService instance
     *
     * @param string $templateDir The main (plugins) template dir (eg plugin/templates)
     * @param string|null $templateFolderName Name of the folder to search for in themes to allow overwriting templates
     *                    If null, themes cannot overwrite templates
     */
    public function __construct(string $templateDir, ?string $templateFolderName = null)
    {
        $this->templateDir = $templateDir;
        if ($templateFolderName) {
            $this->templatePaths = [
                get_stylesheet_directory() . DIRECTORY_SEPARATOR . $templateFolderName,
                get_template_directory() . DIRECTORY_SEPARATOR . $templateFolderName,
                $templateDir,
            ];
        } else {
            $this->templatePaths = [$templateDir];
        }
    }

    /**
     * Get a rendered template, provides hooks to filter args, template and output
     *
     * @uses render
     *
     * @param string|array $template Template name(s) to be rendered (@see locateTemplate)
     * @param array $data Data passed to the template as $data variable
     * @param bool $extractData Whether to extract the data array
     *                          (eg for LearnDash templates, to directly access variables)
     * @return string
     */
    public function get($template, array $data, bool $extractData = false): string
    {
        ob_start();
        $this->render($template, $data, $extractData);
        $output = ob_get_clean();

        /**
         * Filters the template output if it's returned (and not output)
         *
         * @param string $output The output of the template (HTML)
         * @param string $template The template being rendered
         * @param mixed $data The data passed to the template
         * @param TemplateService $templateService TemplateService being used
         */
        $output = $this->eventManager->apply_filters('bizexaminer/template/output', $output, $template, $data, $this);
        return $output;
    }

    /**
     * Renders a rendered template, provides hooks to filter args, template and output
     *
     * @uses locateTemplate
     *
     * @param string|array $template Template name(s) to be rendered (@see locateTemplate)
     * @param array $data Data passed to the template as $data variable
     * @param bool $extractData Whether to extract the data array
     *                          (eg for LearnDash templates, to directly access variables)
     * @return void
     */
    public function render($template, array $data, bool $extractData = false): void
    {
        /**
         * Allows the template-rendering to be short-circuited, by returning a non-null value.
         *
         * @param string|null $pre_render The pre-rendered content. Default null.
         * @param string $template   The template being rendered
         * @param array $data       The data passed to the template
         * @param TemplateService $templateService TemplateService being used
         */
        $preRender = $this->eventManager->apply_filters(
            'bizexaminer/template/pre_render',
            null,
            $template,
            $data,
            $this
        );

        if (!is_null($preRender)) {
            return;
        }

        /**
         * Filters the data passed to the template
         *
         * @param mixed $data The data passed to the template
         * @param string $template The template being rendered
         * @param TemplateService $templateService TemplateService being used
         */
        $data = $this->eventManager->apply_filters('bizexaminer/template/data', $data, $template, $this);

        /**
         * Allows doing something / outputting something before the template is located and rendered
         *
         * @param string $template The template being rendered
         * @param array $data The data passed to the template
         * @param TemplateService $templateService TemplateService being used
         */
        $this->eventManager->do_action('bizexaminer/template/before', $template, $data, $this);

        $templateFile = $this->locateTemplate($template);
        if ($templateFile) {
            if ($extractData) {
                //phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- only use in template
                extract($data, EXTR_SKIP);
            }
            include($templateFile);
        }

        /**
         * Allows doing something / outputting something after the template is located and rendered
         *
         * @param string $template The template being rendered
         * @param array $data The data passed to the template
         * @param TemplateService $templateService TemplateService being used
         */
        $this->eventManager->do_action('bizexaminer/template/after', $template, $data, $this);
    }

    /**
     * Finds a template in the configured templatePaths based on one or more template names
     * Similar to get_template_part from WP Core
     *
     * @param string|array $templates Template name(s) to be rendered (relative to templatePaths)
     * @return string|null The first found template, or null if no found
     */
    public function locateTemplate($templates): ?string
    {
        $templates = (array) $templates;

        // custom implementation of WP Core load_template to use template paths in this service
        $located = null;
        foreach ($templates as $templateName) {
            if (!$templateName) {
                continue;
            }
            foreach ($this->templatePaths as $templatePath) {
                if (file_exists($templatePath . DIRECTORY_SEPARATOR . $templateName)) {
                    $located = $templatePath . DIRECTORY_SEPARATOR . $templateName;
                    break 2;
                }
                if (file_exists($templatePath . DIRECTORY_SEPARATOR . $templateName . '.php')) {
                    $located = $templatePath . DIRECTORY_SEPARATOR . $templateName . '.php';
                    break 2;
                }
            }
        }

        /**
         * Allows filtering the located template
         *
         * @param string $located The path of the located template
         * @param array $templates The names of the templates requested / searched
         * @param TemplateService $templateService TemplateService being used
         */
        $located = $this->eventManager->apply_filters('bizexaminer/template', $located, $templates, $this);
        if (count($templates) === 1) {
            /**
             * Allows filtering the located template on a specific searched template
             *
             * @triggers bizexaminer/template/template=$template
             *
             * @param string $located The path of the located template
             * @param array $templates  The names of the templates requested / searched
             * @param TemplateService $templateService TemplateService being used
             */
            $located = $this->eventManager->apply_filters(
                "bizexaminer/template/template={$templates[0]}",
                $located,
                $templates,
                $this
            );
        }

        return $located;
    }
}
