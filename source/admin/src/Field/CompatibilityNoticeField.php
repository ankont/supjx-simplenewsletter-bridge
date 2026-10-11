<?php
namespace SuperSoft\Component\Newsletterbridge\Administrator\Field;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use SuperSoft\Component\Newsletterbridge\Site\Service\Dependency;

/** Diagnostics stay available in Options when the component has no admin menu. */
class CompatibilityNoticeField extends FormField
{
    protected $type = 'CompatibilityNotice';
    protected function getLabel() { return ''; }
    protected function getInput()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('administrator') || !$app->getIdentity()->authorise('core.options', 'com_newsletterbridge')) {
            return '';
        }
        try {
            Dependency::factory();
            $messages = Dependency::warnings();
            $class = 'alert-warning';
        } catch (\Throwable $e) {
            $messages = [str_starts_with($e->getMessage(), 'COM_NEWSLETTERBRIDGE_DEPENDENCY_')
                ? $e->getMessage() : 'COM_NEWSLETTERBRIDGE_DEPENDENCY_INCOMPATIBLE'];
            $class = 'alert-danger';
        }
        $html = '';
        foreach ($messages as $message) {
            $html .= '<div class="alert ' . $class . '" role="alert">'
                . htmlspecialchars(Text::_($message), ENT_QUOTES, 'UTF-8') . '</div>';
        }
        return $html;
    }
}
