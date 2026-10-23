<?php
namespace SuperSoft\Component\Newsletterbridge\Site\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use SaintArt\Component\SimpleNewsletter\Administrator\Helper\NewsletterHelper;
use SaintArt\Component\SimpleNewsletter\Administrator\Helper\PresetHelper;

/** All entry points repeat resource ACL. Upstream models have no frontend ACL. */
final class Bridge
{
    private $app;
    private $db;
    private $factory;
    public $policy;
    public function __construct($app = null, $db = null, $factory = null)
    {
        $this->app = $app ?: Factory::getApplication();
        $this->policy = new Policy($this->app->getIdentity());
        $this->policy->require($this->policy->enter());
        $this->factory = $factory ?: Dependency::factory();
        $this->db = $db ?: Factory::getContainer()->get(DatabaseInterface::class);
    }
    public function item(int $id, bool $forUpdate = false): object
    {
        if ($id < 1) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
        $db = $this->db;
        $q = $db->getQuery(true)->select($db->quoteName(['id','title','subject','body','published','created_by','created','modified','checked_out','checked_out_time']))
            ->from($db->quoteName('#__simplenewsletter_templates'))->where($db->quoteName('id') . ' = ' . $id);
        $item = $db->setQuery((string) $q . ($forUpdate ? ' FOR UPDATE' : ''))->loadObject();
        if (!$item) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
        $this->policy->require($this->policy->read($item));
        return $item;
    }
    private function templateModel()
    {
        return $this->factory->createModel('Template', 'Administrator', ['ignore_request' => true]);
    }
    private function sender()
    {
        return $this->factory->createModel('Send', 'Administrator', ['ignore_request' => true]);
    }
    private function transaction(callable $work)
    {
        $this->db->transactionStart();
        try { $result = $work(); $this->db->transactionCommit(); return $result; }
        catch (\Throwable $e) { $this->db->transactionRollback(); throw $e; }
    }
    /** A connection-scoped database lock coordinates tabs, sessions and bridge workers. */
    private function sendLock(callable $work)
    {
        $name = 'newsletterbridge:' . substr(hash('sha256', $this->db->getPrefix()), 0, 40);
        $sqlName = $this->db->quote($name);
        if ((int) $this->db->setQuery('SELECT GET_LOCK(' . $sqlName . ', 0)')->loadResult() !== 1) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_BUSY', 409);
        }
        try { return $work(); }
        finally { $this->db->setQuery('SELECT RELEASE_LOCK(' . $sqlName . ')')->loadResult(); }
    }
    public function checkout(int $id): void
    {
        $this->transaction(function () use ($id) {
            $item = $this->item($id, true);
            $this->policy->require($this->policy->edit($item));
            $this->unlocked($item);
            if (!$this->templateModel()->checkout($id)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409); }
        });
    }
    private function unlocked(object $item, bool $mustOwn = false): void
    {
        $owner = (int) ($item->checked_out ?? 0);
        $user = (int) $this->app->getIdentity()->id;
        if (($owner !== 0 && $owner !== $user) || ($mustOwn && $owner !== $user)) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409);
        }
    }
    public function checkin(int $id): void
    {
        $this->transaction(function () use ($id) {
            $item = $this->item($id, true);
            $this->policy->require($this->policy->edit($item));
            $this->unlocked($item, true);
            if (!$this->templateModel()->checkin($id)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409); }
        });
    }
    public function save(int $id, array $raw): int
    {
        // Validate before and after Joomla's configured HTML filtering.
        $data = InputGuard::newsletter($raw);
        $data['body'] = ComponentHelper::filterText($data['body']);
        $data = InputGuard::newsletter($data);
        return $this->transaction(function () use ($id, $data, $raw) {
            $table = $this->templateModel()->getTable();
            if ($id) {
                $item = $this->item($id, true);
                $this->policy->require($this->policy->edit($item));
                $this->unlocked($item, true);
                if (!$table->load($id)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
                $state = (int) $item->published;
            } else {
                $this->policy->require($this->policy->allows('core.create'));
                $state = 0;
            }
            if (array_key_exists('published', $raw)) {
                $requested = InputGuard::state($raw['published']);
                if ($requested !== $state) { $this->policy->require($this->policy->allows('core.edit.state')); }
                $state = $requested;
            }
            $safe = $data + ['id' => $id, 'published' => $state];
            if (!$id) { $safe['created_by'] = (int) $this->app->getIdentity()->id; }
            // AdminModel::save applies com_simplenewsletter state ACL and administrator event context.
            // Use its MVCFactory table instead, with bridge ACL and a strict field allowlist.
            if (!$table->bind($safe) || !$table->check() || !$table->store()) {
                throw new \RuntimeException('COM_NEWSLETTERBRIDGE_SAVE_FAILED', 500);
            }
            $saved = (int) $table->id;
            if ($id && !$this->templateModel()->checkin($saved)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_LOCKED', 409); }
            return $saved;
        });
    }
    public function changeState(int $id, $state): void
    {
        $state = InputGuard::state($state);
        $this->transaction(function () use ($id, $state) {
            $item = $this->item($id, true);
            $this->policy->require($this->policy->edit($item) && $this->policy->allows('core.edit.state'));
            $this->unlocked($item);
            $row = (object) ['id' => $id, 'published' => $state, 'modified' => Factory::getDate()->toSql()];
            $this->db->updateObject('#__simplenewsletter_templates', $row, 'id');
        });
    }
    private function presetScope(int $id): void
    {
        if ($id) { $this->policy->require($this->policy->edit($this->item($id))); }
        else { $this->policy->require($this->policy->allows('core.create')); }
    }
    private function presetLibrary(): PresetLibrary { return new PresetLibrary($this->app, $this->db); }
    public function presets(int $id): array
    {
        $this->presetScope($id);
        return $this->presetLibrary()->choices();
    }
    public function canSavePreset(int $id): bool
    {
        $this->presetScope($id);
        return $this->presetLibrary()->canSave();
    }
    public function preset(int $id, string $preset): array
    {
        $this->presetScope($id);
        return $this->presetLibrary()->get($preset);
    }
    public function savePreset(int $id, array $raw): array
    {
        $this->presetScope($id);
        $data = InputGuard::newsletter($raw);
        return $this->presetLibrary()->save($data['title'], $data['subject'], $data['body']);
    }
    public function preview(int $id): string
    {
        $item = $this->item($id);
        // Generic data only, and no working confirm/unsubscribe tokens.
        $body = EmailPreparation::render((string) $item->body, 'preview');
        return EmailUrls::html(NewsletterHelper::replaceTags($body, ['name' => 'Sample', 'email' => 'sample@example.invalid', 'token' => '']));
    }
    public function test(int $id, string $email): void
    {
        $item = $this->item($id);
        $this->policy->require($this->policy->send($item));
        $this->mailEnabled();
        $email = trim($email);
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 400);
        }
        // Upstream sendTest reads subscriber names/tokens. Never call it from frontend.
        // A sample-only test still reuses the real sender and tag helpers.
        [$from, $name] = NewsletterHelper::getSender();
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_MAIL_DISABLED', 503); }
        $sample = ['name' => 'Sample', 'email' => 'sample@example.invalid', 'token' => ''];
        $body = EmailPreparation::render((string) $item->body, 'test');
        $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
        $mailer->setSender([$from, $name]);
        $reply = NewsletterHelper::getReplyTo();
        if ($reply !== '') { $mailer->addReplyTo($reply, $name); }
        $mailer->addRecipient($email);
        $mailer->setSubject('[TEST] ' . NewsletterHelper::replaceTags($item->subject, $sample));
        $mailer->isHtml(true);
        $mailer->setBody(EmailUrls::html(NewsletterHelper::replaceTags($body, $sample)));
        if ($mailer->Send() !== true) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_SEND_FAILED', 500); }
    }
    private function mailEnabled(): void
    {
        if (!(bool) $this->app->get('mailonline', 1)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_MAIL_DISABLED', 503); }
    }
    public function start(int $id, bool $confirmed): array
    {
        $item = $this->item($id);
        $this->policy->require($this->policy->send($item));
        if (!$confirmed) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_CONFIRM_REQUIRED', 400); }
        $this->mailEnabled();
        return $this->sendLock(function () use ($id) {
            return $this->transaction(function () use ($id) {
                $item = $this->item($id, true);
                $this->policy->require($this->policy->send($item));
                $this->unlocked($item);
                if ((int) $item->published !== 1) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_PUBLISH_FIRST', 409); }
                $db = $this->db;
                $q = $db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__simplenewsletter_jobs'))
                    ->where($db->quoteName('template_id') . ' = ' . $id)->whereIn($db->quoteName('state'), [0, 1]);
                if ($db->setQuery($q)->loadResult()) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_ACTIVE_JOB', 409); }
                // Render once, before creating the queue. Every recipient receives
                // this snapshot, even when articles change between batches.
                $body = EmailUrls::html(EmailPreparation::render((string) $item->body, 'send'));
                $job = $this->sender()->createJob($id);
                $snapshot = (object) ['id' => (int) $job->id, 'body' => $body];
                $db->updateObject('#__simplenewsletter_jobs', $snapshot, 'id');
                return $this->progress($job);
            });
        });
    }
    private function job(int $id): object
    {
        if ($id < 1) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
        $db = $this->db;
        // Never select job body, recipient rows, errors or tokens for a status response.
        $q = $db->getQuery(true)->select($db->quoteName(['id','template_id','created_by','state','total','sent','failed','created','finished']))
            ->from($db->quoteName('#__simplenewsletter_jobs'))->where($db->quoteName('id') . ' = ' . $id);
        $job = $db->setQuery($q)->loadObject();
        if (!$job) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_NOT_FOUND', 404); }
        $this->policy->require($this->policy->job($job, $this->item((int) $job->template_id)));
        return $job;
    }
    private function progress(object $job): array
    {
        return ['jobId' => (int) $job->id, 'templateId' => (int) $job->template_id,
            'state' => (int) $job->state, 'total' => (int) $job->total,
            'sent' => (int) $job->sent, 'failed' => (int) $job->failed,
            'done' => in_array((int) $job->state, [2, 3], true)];
    }
    public function status(int $id): array { return $this->progress($this->job($id)); }
    /** Recover summary counters after a worker stopped between queue updates and batch summary. */
    private function reconcile(object $job): object
    {
        $db = $this->db;
        $q = $db->getQuery(true)->select($db->quoteName('state') . ', COUNT(*) AS ' . $db->quoteName('row_count'))
            ->from($db->quoteName('#__simplenewsletter_queue'))
            ->where($db->quoteName('job_id') . ' = ' . (int) $job->id)->group($db->quoteName('state'));
        $counts = [0 => 0, 1 => 0, 2 => 0];
        foreach ($db->setQuery($q)->loadObjectList() ?: [] as $row) {
            if (!array_key_exists((int) $row->state, $counts)) { return $job; }
            $counts[(int) $row->state] = (int) $row->row_count;
        }
        // A purged or externally modified queue is not evidence for replacing a retained summary.
        if (array_sum($counts) !== (int) $job->total) { return $job; }
        if ((int) $job->sent !== $counts[1] || (int) $job->failed !== $counts[2]) {
            $update = (object) ['id' => (int) $job->id, 'sent' => $counts[1], 'failed' => $counts[2]];
            $db->updateObject('#__simplenewsletter_jobs', $update, 'id');
            $job->sent = $counts[1]; $job->failed = $counts[2];
        }
        return $job;
    }
    private function normaliseJobBody(int $id): void
    {
        // Called only after job ACL checks, under the bridge send lock. Keep the
        // job snapshot: never replace its content with a subsequently edited template.
        $q = $this->db->getQuery(true)->select($this->db->quoteName('body'))
            ->from($this->db->quoteName('#__simplenewsletter_jobs'))
            ->where($this->db->quoteName('id') . ' = ' . $id);
        $row = $this->db->setQuery($q)->loadObject();
        if (!$row || !isset($row->body)) { return; }
        EmailPreparation::assertPrepared((string) $row->body, true);
        $body = EmailUrls::html((string) $row->body);
        if ($body !== $row->body) {
            $update = (object) ['id' => $id, 'body' => $body];
            $this->db->updateObject('#__simplenewsletter_jobs', $update, 'id');
        }
    }
    public function process(int $id): array
    {
        $this->job($id);
        $this->mailEnabled();
        return $this->sendLock(function () use ($id) {
            $job = $this->job($id);
            if (in_array((int) $job->state, [2, 3], true)) { return $this->progress($job); }
            if (!in_array((int) $job->state, [0, 1], true)) { throw new \RuntimeException('COM_NEWSLETTERBRIDGE_INVALID', 409); }
            $this->reconcile($job);
            $this->normaliseJobBody($id);
            // Bound request duration: upstream throttle can otherwise take 400 seconds.
            $params = ComponentHelper::getParams('com_simplenewsletter');
            $throttle = max(0, (int) $params->get('throttle', 0));
            $limit = max(1, min(25, (int) $params->get('batch_size', 25), (int) floor(10000 / max(1, $throttle))));
            // Upstream finish() purges queues globally. Suppress that unrelated side effect
            // for the duration of this request; stored Simple Newsletter settings stay untouched.
            $retention = $params->get('queue_retention', 30);
            $params->set('queue_retention', 0);
            try { $this->sender()->processBatch($id, $limit); }
            finally {
                $params->set('queue_retention', $retention);
                $this->reconcile($this->job($id));
            }
            return $this->status($id);
        });
    }
    public function cancel(int $id): array
    {
        $this->job($id);
        return $this->sendLock(function () use ($id) {
            $job = $this->job($id);
            if (!in_array((int) $job->state, [2, 3], true)) { $this->sender()->cancelJob($id); }
            return $this->status($id);
        });
    }
    public function jobs(int $templateId, int $offset = 0): array
    {
        $item = $this->item($templateId);
        $this->policy->require($this->policy->send($item));
        $db = $this->db;
        $q = $db->getQuery(true)->from($db->quoteName('#__simplenewsletter_jobs'))
            ->where($db->quoteName('template_id') . ' = ' . $templateId);
        if (!$this->policy->allows('core.edit')) { $q->where($db->quoteName('created_by') . ' = ' . (int) $this->app->getIdentity()->id); }
        $total = (int) $db->setQuery((clone $q)->select('COUNT(*)'))->loadResult();
        $rows = $db->setQuery($q->select($db->quoteName(['id','template_id','created_by','state','total','sent','failed','created','finished']))
            ->order($db->quoteName('id') . ' DESC'), max(0, $offset), 20)->loadObjectList();
        return ['total' => $total, 'items' => array_map(fn ($job) => $this->progress($job) + ['created' => $job->created], $rows ?: [])];
    }
}
