<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Newsletter;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use SuperSoft\Component\Newsletterbridge\Site\View\BridgeView;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
class HtmlView extends BridgeView
{
    public $item;
    public $form;
    public $presets;
    public $canSavePreset;
    public function display($tpl = null)
    {
        $this->initialise();
        $app = Factory::getApplication();
        $id = InputGuard::id($app->getInput()->get('id', '0', 'raw'), true);
        if ($id) {
            $this->item = $this->bridge->item($id);
            $this->policy->require($this->policy->edit($this->item));
            if ((int) $this->item->checked_out !== (int) $app->getIdentity()->id) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409); }
        } else {
            $this->policy->require($this->policy->allows('core.create'));
            $this->item = (object) ['id' => 0, 'title' => '', 'subject' => '', 'body' => '', 'published' => 0];
        }
        $data = (array) $this->item;
        $draft = $app->getUserState('com_newsletterbridge.edit.' . $id, []);
        foreach ((array) $draft as $key => $value) {
            if (in_array($key, ['title','subject','body','published'], true) && is_scalar($value)) { $data[$key] = $value; }
        }
        $this->form = $this->getModel()->getForm($data);
        if (!$this->form) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOAD_FAILED', 500); }
        $this->presets = $this->bridge->presets($id);
        $this->canSavePreset = $this->bridge->canSavePreset($id);
        return parent::display($tpl);
    }
}
