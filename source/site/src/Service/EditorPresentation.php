<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/** Sources 4.2.0 public per-editor contract, declared before editor rendering. */
final class EditorPresentation
{
    public static function configure($form): void
    {
        $field = $form->getField('body');
        if (!$field || !$field->id) { return; }
        $id = (string) $field->id;
        $document = Factory::getApplication()->getDocument();
        // Form validation and CLI callers need no browser integration.
        if (!$document || $document->getType() !== 'html') { return; }
        $document->addScriptOptions('plg_editorsxtd_sourcesbutton.preview', ['editors' => [$id => ['presentation' => 'email']]]);
        $document->addScriptOptions('com_newsletterbridge.editorPresentation', ['editors' => [$id => 'email']]);
        $document->getWebAssetManager()->registerAndUseScript('com_newsletterbridge.editor-presentation', 'com_newsletterbridge/editor-presentation.js', ['version' => 'auto'], ['defer' => true], ['core']);
    }
}
