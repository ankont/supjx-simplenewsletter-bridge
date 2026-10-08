<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/** Opt-in public snapshots. Never queries subscribers or the recipient queue. */
final class Archive
{
    private $app;
    private $db;
    private $bridge;
    public function __construct($app = null, $db = null, $bridge = null)
    {
        if ($db === null) { Dependency::factory(); }
        $this->app = $app ?? Factory::getApplication();
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $this->bridge = $bridge;
    }
    private function bridge(): Bridge
    {
        return $this->bridge ??= new Bridge($this->app, $this->db);
    }
    private function job(int $id, bool $lock = false): object
    {
        $this->db->setQuery('SELECT id,template_id,state,sent,finished FROM #__simplenewsletter_jobs WHERE id=' . $id . ($lock ? ' FOR UPDATE' : ''));
        $job = $this->db->loadObject();
        if (!$job) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
        $bridge = $this->bridge();
        $item = $bridge->item((int) $job->template_id);
        $bridge->policy->require($bridge->policy->archive($item));
        return $job;
    }
    private function eligible(object $job): void
    {
        if ((int) $job->state !== 2 || (int) $job->sent < 1) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_ARCHIVE_INELIGIBLE', 409); }
    }
    public function editions(int $templateId, int $offset = 0): array
    {
        $bridge = $this->bridge();
        $item = $bridge->item($templateId);
        $bridge->policy->require($bridge->policy->archive($item));
        $where = ' FROM #__simplenewsletter_jobs j LEFT JOIN #__newsletterbridge_archive a ON a.job_id=j.id WHERE j.template_id=' . $templateId . ' AND j.state=2 AND j.sent>0';
        $this->db->setQuery('SELECT COUNT(*)' . $where); $total = (int) $this->db->loadResult();
        $this->db->setQuery('SELECT j.id,j.subject,j.finished,COALESCE(a.published,0) AS published' . $where . ' ORDER BY j.id DESC', max(0, $offset), 20);
        return ['items' => $this->db->loadObjectList(), 'total' => $total];
    }
    public function draft(int $id): object
    {
        $job = $this->job($id); $this->eligible($job);
        $this->db->setQuery('SELECT job_id,title,subject,body,published,revision FROM #__newsletterbridge_archive WHERE job_id=' . $id);
        $draft = $this->db->loadObject();
        if (!$draft) {
            $this->db->setQuery('SELECT subject,body FROM #__simplenewsletter_jobs WHERE id=' . $id);
            $source = $this->db->loadObject();
            $draft = (object) ['job_id' => $id, 'title' => $source->subject, 'subject' => $source->subject, 'body' => $source->body, 'published' => 0, 'revision' => 0];
        }
        $name = Text::_('COM_NEWSLETTERBRIDGE_SUBSCRIBER');
        $draft->title = PublicContent::text($draft->title, $name);
        $draft->subject = PublicContent::text($draft->subject, $name);
        $draft->body = PublicContent::html($draft->body, $name);
        $draft->template_id = (int) $job->template_id;
        return $draft;
    }
    public function save(int $id, int $revision, array $input, bool $publish, bool $reviewed): void
    {
        // Permission is checked before validation, then again under the row lock.
        $this->job($id);
        if ($publish && !$reviewed) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_ARCHIVE_REVIEW_REQUIRED', 400); }
        $data = InputGuard::newsletter($input);
        $name = Text::_('COM_NEWSLETTERBRIDGE_SUBSCRIBER');
        $data['title'] = PublicContent::text($data['title'], $name);
        $data['subject'] = PublicContent::text($data['subject'], $name);
        $data['body'] = PublicContent::html($data['body'], $name);
        $data = InputGuard::newsletter($data);
        $this->db->transactionStart();
        try {
            $job = $this->job($id, true); $this->eligible($job);
            $this->db->setQuery('SELECT revision FROM #__newsletterbridge_archive WHERE job_id=' . $id . ' FOR UPDATE');
            $current = (int) $this->db->loadResult();
            if ($current !== $revision) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_ARCHIVE_CONFLICT', 409); }
            $row = (object) ['job_id' => $id, 'title' => $data['title'], 'subject' => $data['subject'], 'body' => $data['body'],
                'published' => (int) $publish, 'revision' => $current + 1, 'modified' => Factory::getDate()->toSql(),
                'modified_by' => (int) $this->app->getIdentity()->id, 'published_at' => $publish ? Factory::getDate()->toSql() : null];
            if ($current) { $this->db->updateObject('#__newsletterbridge_archive', $row, 'job_id', true); }
            else { $this->db->insertObject('#__newsletterbridge_archive', $row); }
            $this->db->transactionCommit();
        } catch (\Throwable $e) { $this->db->transactionRollback(); throw $e; }
    }
    public function withdraw(int $id, int $revision): void
    {
        $this->db->transactionStart();
        try {
            $this->job($id, true);
            $this->db->setQuery('SELECT revision FROM #__newsletterbridge_archive WHERE job_id=' . $id . ' FOR UPDATE');
            if ((int) $this->db->loadResult() !== $revision || $revision < 1) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_ARCHIVE_CONFLICT', 409); }
            $this->db->setQuery('UPDATE #__newsletterbridge_archive SET published=0, published_at=NULL, revision=revision+1, modified='
                . $this->db->quote(Factory::getDate()->toSql()) . ', modified_by=' . (int) $this->app->getIdentity()->id . ' WHERE job_id=' . $id)->execute();
            $this->db->transactionCommit();
        } catch (\Throwable $e) { $this->db->transactionRollback(); throw $e; }
    }
    private function publicFrom(): string
    {
        return ' FROM #__newsletterbridge_archive a INNER JOIN #__simplenewsletter_jobs j ON j.id=a.job_id INNER JOIN #__simplenewsletter_templates t ON t.id=j.template_id WHERE a.published=1 AND j.state=2 AND j.sent>0';
    }
    public function listing(int $offset = 0): array
    {
        $this->db->setQuery('SELECT COUNT(*)' . $this->publicFrom()); $total = (int) $this->db->loadResult();
        $this->db->setQuery('SELECT a.job_id,a.title,j.finished AS sent_at' . $this->publicFrom() . ' ORDER BY j.finished DESC,a.job_id DESC', max(0, $offset), 20);
        return ['items' => $this->db->loadObjectList(), 'total' => $total];
    }
    public function issue(int $id): object
    {
        $this->db->setQuery('SELECT a.job_id,a.title,a.subject,a.body,j.finished AS sent_at' . $this->publicFrom() . ' AND a.job_id=' . $id);
        $item = $this->db->loadObject();
        if (!$item) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
        $item->body = PublicContent::html($item->body, Text::_('COM_NEWSLETTERBRIDGE_SUBSCRIBER'));
        return $item;
    }
}
