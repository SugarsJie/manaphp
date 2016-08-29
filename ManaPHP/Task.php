<?php
namespace ManaPHP;

use ManaPHP\Task\Exception;
use ManaPHP\Task\Metadata;

/**
 * Class Task
 *
 * @package ManaPHP
 *
 * @property \ManaPHP\Task\MetadataInterface $tasksMetadata
 * @property \ManaPHP\LoggerInterface        $logger
 * @property \ManaPHP\Http\ResponseInterface $response
 */
abstract class Task extends Component implements TaskInterface
{
    const STATUS_NONE = 0;
    const STATUS_RUNNING = 1;
    const STATUS_STOP = 2;

    const STOP_TYPE_CANCEL = 1;
    const STOP_TYPE_EXCEPTION = 2;
    const STOP_TYPE_MEMORY_LIMIT = 3;

    /**
     * @var int
     */
    protected $_memoryLimit = 16;

    /**
     * @param int $timeLimit
     *
     * @return void
     * @throws \ManaPHP\Task\Exception
     */
    public function start($timeLimit = 0)
    {
        $this->logger->info('[%task%] starting...', ['task' => get_called_class()]);

        /** @noinspection TypeUnsafeComparisonInspection */
        if ($this->tasksMetadata->get($this, Metadata::FIELD_STATUS) == Task::STATUS_RUNNING) {
            throw new Exception('Task is existed.');
        }

        $start_time = time();

        $this->tasksMetadata->reset($this);
        $this->tasksMetadata->set($this, Metadata::FIELD_STATUS, self::STATUS_RUNNING);
        $this->tasksMetadata->set($this, Metadata::FIELD_CLASS, get_class($this));
        $this->tasksMetadata->set($this, Metadata::FIELD_START_TIME, date('Y-m-d H:i:s', $start_time));

        $stop_reason = '';
        $stop_time = 0;
        $stop_type = 0;

        try {
            $this->logger->info('task start successfully. memory: ' . implode(', ', [
                    round(memory_get_usage(false) / 1024) . 'k',
                    round(memory_get_usage(true) / 1024) . 'k',
                    round(memory_get_peak_usage(false) / 1024) . 'k',
                    round(memory_get_peak_usage(true) / 1024) . 'k'
                ]));

            ignore_user_abort(true);
            set_time_limit($timeLimit);
            $this->response->setHeader('Connection', 'close');
            $this->response->setHeader('Content-Length', strlen($this->response->getContent()));

            $this->response->send();

            while (ob_get_level()) {
                ob_end_flush();
            }

            flush();

            while (true) {
                $this->tasksMetadata->set($this, Metadata::FIELD_KEEP_ALIVE_TIME, date('Y-m-d H:i:s'));
                $this->tasksMetadata->set($this, Metadata::FIELD_MEMORY_PEAK_USAGE, round(memory_get_peak_usage() / 1024 / 1024, 3) . 'MB');

                $this->run();

                if ($this->tasksMetadata->exists($this, Metadata::FIELD_CANCEL_FLAG)) {
                    $stop_time = time();
                    $stop_type = self::STOP_TYPE_CANCEL;
                    $stop_reason = 'CANCEL';
                    $this->logger->info('[%task%]: ' . $stop_reason, ['task' => get_called_class()]);
                    break;
                }

                if (memory_get_usage(true) > $this->_memoryLimit * 1024 * 1024) {
                    $stop_time = time();
                    $stop_type = self::STOP_TYPE_MEMORY_LIMIT;
                    $stop_reason = 'MEMORY LIMIT';
                    $this->logger->fatal('[%task%]: ' . $stop_reason, ['task' => get_called_class()]);
                    break;
                }
            }
        } catch (\ManaPHP\Exception $e) {
            $stop_time = time();
            $stop_type = self::STOP_TYPE_EXCEPTION;
            $stop_reason = 'EXCEPTION: ' . json_encode($e->dump(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->logger->fatal('[%task%]: ' . $stop_reason, ['task' => get_called_class()]);
        }

        $duration_time = $stop_time - $start_time;

        $this->tasksMetadata->set($this, Metadata::FIELD_STATUS, Task::STATUS_STOP);
        $this->tasksMetadata->set($this, Metadata::FIELD_STOP_TIME, date('Y-m-d H:i:s', $stop_time));
        $this->tasksMetadata->set($this, Metadata::FIELD_DURATION_TIME, $duration_time);
        /** @noinspection SummerTimeUnsafeTimeManipulationInspection */
        $this->tasksMetadata->set($this, Metadata::FIELD_DURATION_TIME_HUMAN, round($duration_time / 3600 / 24) . ' days ' . gmstrftime('%H:%M:%S', $duration_time % (3600 * 24)));
        $this->tasksMetadata->set($this, Metadata::FIELD_STOP_REASON, $stop_reason);
        $this->tasksMetadata->set($this, Metadata::FIELD_STOP_TYPE, $stop_type);
    }
}