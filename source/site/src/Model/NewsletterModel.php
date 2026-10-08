<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use SuperSoft\Component\Newsletterbridge\Site\Service\Bridge;

class NewsletterModel extends FormModel
{
    public function getForm($data = [], $loadData = true)
    {
        $bridge = new Bridge();
        $form = $this->loadForm('com_newsletterbridge.newsletter', JPATH_COMPONENT . '/forms/newsletter.xml', ['control' => 'jform', 'load_data' => false]);
        if (!$form) { return false; }
        $editor = \Joomla\CMS\Component\ComponentHelper::getParams('com_newsletterbridge')->get('editor', 'tinymce');
        $form->setFieldAttribute('body', 'editor', $editor . '|none');
        if (!$bridge->policy->allows('core.edit.state')) { $form->removeField('published'); }
        if ($loadData) { $form->bind($data); }
        return $form;
    }
}
