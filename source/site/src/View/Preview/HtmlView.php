<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Preview;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use SuperSoft\Component\Newsletterbridge\Site\View\BridgeView;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
class HtmlView extends BridgeView
{
    public $item;
    public $body;
    public function display($tpl = null)
    {
        $this->initialise();
        $id = InputGuard::id(Factory::getApplication()->getInput()->get('id', '', 'raw'));
        $this->item = $this->bridge->item($id);
        $body = $this->bridge->preview($id);
        // This CSP is the first element inside srcdoc, before any saved markup.
        $this->body = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src https: http: data:; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'"></head><body>' . $body . '</body></html>';
        return parent::display($tpl);
    }
}
