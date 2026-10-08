<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Publications;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use SuperSoft\Component\Newsletterbridge\Site\Service\Archive;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
use SuperSoft\Component\Newsletterbridge\Site\View\BridgeView;
class HtmlView extends BridgeView
{
    public $item; public $items; public $pagination;
    public function display($tpl = null)
    {
        $this->initialise(); $input = Factory::getApplication()->getInput();
        $id = InputGuard::id($input->get('id', '', 'raw'));
        $this->item = $this->bridge->item($id);
        $offset = min(1000000, max(0, $input->getInt('limitstart')));
        $result = (new Archive())->editions($id, $offset);
        $this->items = $result['items']; $this->pagination = new Pagination($result['total'], $offset, 20);
        return parent::display($tpl);
    }
}
