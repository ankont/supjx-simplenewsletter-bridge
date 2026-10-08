<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Archive;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use SuperSoft\Component\Newsletterbridge\Site\Service\Archive;
class HtmlView extends BaseView
{
    public $items; public $pagination; public $showName; public $return;
    public function url(string $view = 'archive', int $id = 0): string
    {
        $itemid = Factory::getApplication()->getInput()->getInt('Itemid');
        return Route::_('index.php?option=com_newsletterbridge&view=' . $view . ($id ? '&id=' . $id : '') . ($itemid > 0 ? '&Itemid=' . $itemid : ''));
    }
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $offset = min(1000000, max(0, $app->getInput()->getInt('limitstart')));
        $result = (new Archive())->listing($offset);
        $this->items = $result['items'];
        $this->pagination = new Pagination($result['total'], $offset, 20);
        $this->showName = ComponentHelper::getParams('com_simplenewsletter')->get('show_name', 1);
        $this->return = 'index.php?option=com_newsletterbridge&view=archive' . ($app->getInput()->getInt('Itemid') > 0 ? '&Itemid=' . $app->getInput()->getInt('Itemid') : '');
        $app->getDocument()->getWebAssetManager()->registerAndUseStyle('com_newsletterbridge.site', 'com_newsletterbridge/bridge.css');
        return parent::display($tpl);
    }
}
