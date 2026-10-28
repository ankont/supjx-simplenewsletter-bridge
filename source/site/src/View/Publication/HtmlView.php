<?php
namespace SuperSoft\Component\Newsletterbridge\Site\View\Publication;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Component\ComponentHelper;
use SuperSoft\Component\Newsletterbridge\Site\Service\Archive;
use SuperSoft\Component\Newsletterbridge\Site\Service\InputGuard;
use SuperSoft\Component\Newsletterbridge\Site\View\BridgeView;
class HtmlView extends BridgeView
{
    public $item; public $form;
    public function display($tpl = null)
    {
        $this->initialise();
        $id = InputGuard::id(Factory::getApplication()->getInput()->get('id', '', 'raw'));
        $this->item = (new Archive())->draft($id);
        $this->form = Form::getInstance('com_newsletterbridge.publication', JPATH_SITE . '/components/com_newsletterbridge/forms/publication.xml', ['control' => 'jform']);
        $editor = ComponentHelper::getParams('com_newsletterbridge')->get('editor', 'tinymce');
        $this->form->setFieldAttribute('body', 'editor', $editor . '|none');
        $this->form->bind((array) $this->item);
        return parent::display($tpl);
    }
}
