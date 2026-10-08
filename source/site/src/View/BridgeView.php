<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;

class BridgeView extends HtmlView
{
    protected $bridge;
    public $policy;
    protected function initialise(): void
    {
        $this->bridge = new Bridge();
        $this->policy = $this->bridge->policy;
        Factory::getApplication()->allowCache(false);
        Factory::getApplication()->setHeader('Cache-Control', 'private, no-store', true);
        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->registerAndUseStyle('com_newsletterbridge.site', 'com_newsletterbridge/bridge.css')
            ->registerAndUseScript('com_newsletterbridge.site', 'com_newsletterbridge/bridge.js', [], ['defer' => true], ['core']);
        $input = Factory::getApplication()->getInput();
        Factory::getApplication()->getDocument()->addScriptOptions('com_newsletterbridge', [
            'endpoint' => Route::_('index.php?option=com_newsletterbridge&format=json' . ($input->getInt('Itemid') ? '&Itemid=' . $input->getInt('Itemid') : ''), false),
            'token' => Session::getFormToken(),
        ]);
    }
    public function url(string $view = 'newsletters', int $id = 0): string
    {
        $itemid = Factory::getApplication()->getInput()->getInt('Itemid');
        return Route::_('index.php?option=com_newsletterbridge&view=' . $view . ($id ? '&id=' . $id : '') . ($itemid > 0 ? '&Itemid=' . $itemid : ''));
    }
    public function returnUrl(): string
    {
        $itemid = Factory::getApplication()->getInput()->getInt('Itemid');
        return 'index.php?option=com_newsletterbridge&view=newsletters' . ($itemid > 0 ? '&Itemid=' . $itemid : '');
    }
}
