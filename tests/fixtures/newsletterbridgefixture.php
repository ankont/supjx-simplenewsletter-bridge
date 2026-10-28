<?php
/** TEST ONLY: temporary newsletter provider, never included in the installable ZIP. */
defined('_JEXEC') or die;

class PlgNewsletterNewsletterbridgefixture extends \Joomla\CMS\Plugin\CMSPlugin implements \Joomla\Event\SubscriberInterface
{
    public static $calls = 0;
    public static $mode = 'render';
    public static $title = 'Original provider article';

    public static function getSubscribedEvents(): array { return ['onNewsletterPrepareEmail' => 'prepareEmail']; }

    public function prepareEmail(\Joomla\Event\EventInterface $event): void
    {
        self::$calls++;
        $document = $event->getArgument('document');
        if ($event->getArgument('context') !== 'com_newsletterbridge.newsletter' || $document->get('version') !== 1) { return; }
        if (\Joomla\CMS\Factory::getApplication()->getIdentity()->id !== 0 || !$document->get('publicOnly')) { throw new \RuntimeException('Provider requires public identity'); }
        if (self::$mode === 'throw') { throw new \RuntimeException('Synthetic private provider detail'); }
        if (self::$mode === 'bad-policy') { $document->set('publicOnly', false); return; }
        if (self::$mode === 'unresolved') { $document->set('unresolved', ['bridgefixture']); return; }
        $html = $document->get('html');
        if (!str_contains($html, '{bridgefixture:')) { return; }
        $html = str_replace('{bridgefixture:{"article":1}}', '<p>' . htmlspecialchars(self::$title, ENT_QUOTES, 'UTF-8') . ' {name}</p><img src="images/provider.jpg">', $html);
        $document->set('html', $html);
        $document->set('usage.items', $document->get('usage.items') + (self::$mode === 'large' ? 120 : 1));
        $document->set('usage.tokens', $document->get('usage.tokens') + (self::$mode === 'large' ? 16 : 1));
    }
}
