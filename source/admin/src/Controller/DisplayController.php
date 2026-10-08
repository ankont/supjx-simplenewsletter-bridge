<?php
namespace SuperSoft\Component\Newsletterbridge\Administrator\Controller;
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Toolbar\ToolbarHelper;

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
        echo '<p>' . htmlspecialchars(Text::_('COM_NEWSLETTERBRIDGE_ADMIN_HELP'), ENT_QUOTES, 'UTF-8') . '</p>';
        return $this;
    }
}
