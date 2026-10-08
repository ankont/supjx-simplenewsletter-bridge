<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Issue;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseView;
use Joomla\CMS\Router\Route;
use SuperSoft\Component\Newsletterbridge\Site\Service\Archive;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
class HtmlView extends BaseView
{
    public $item; public $back;
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $this->item = (new Archive())->issue(InputGuard::id($app->getInput()->get('id', '', 'raw')));
        $itemid = $app->getInput()->getInt('Itemid');
        $this->back = Route::_('index.php?option=com_newsletterbridge&view=archive' . ($itemid > 0 ? '&Itemid=' . $itemid : ''));
        $app->getDocument()->setTitle($this->item->title);
        $app->getDocument()->getWebAssetManager()->registerAndUseStyle('com_newsletterbridge.site', 'com_newsletterbridge/bridge.css');
        return parent::display($tpl);
    }
}
