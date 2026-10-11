<?php
namespace SuperSoft\Component\Newsletterbridge\Administrator\Controller;
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Toolbar\ToolbarHelper;
use SuperSoft\Component\Newsletterbridge\Site\Service\Dependency;

class DisplayController extends BaseController
{
    public function display($cachable = false, $urlparams = [])
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_newsletterbridge')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        ToolbarHelper::title(Text::_('COM_NEWSLETTERBRIDGE'));
        if ($this->app->getIdentity()->authorise('core.options', 'com_newsletterbridge')) {
            ToolbarHelper::preferences('com_newsletterbridge');
        }
        try {
            Dependency::factory();
            foreach (Dependency::warnings() as $key) {
                echo '<div class="alert alert-warning" role="alert">' . htmlspecialchars(Text::_($key), ENT_QUOTES, 'UTF-8') . '</div>';
            }
        } catch (\Throwable $e) {
            $key = str_starts_with($e->getMessage(), 'COM_NEWSLETTERBRIDGE_DEPENDENCY_')
                ? $e->getMessage() : 'COM_NEWSLETTERBRIDGE_DEPENDENCY_INCOMPATIBLE';
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars(Text::_($key), ENT_QUOTES, 'UTF-8') . '</div>';
        }
        echo '<p>' . htmlspecialchars(Text::_('COM_NEWSLETTERBRIDGE_ADMIN_HELP'), ENT_QUOTES, 'UTF-8') . '</p>';
        return $this;
    }
}
