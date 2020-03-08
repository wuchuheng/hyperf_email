<?php
/**
 * 监听HELO OR EHELO指令事件并返回回复消息.
 *
 * @author wuchuheng<wuchuheng@163.com>
 */
namespace App\Smtp\Listener;

use App\Smtp\Util\Session;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use \Redis;
    use \App\Smtp\Event\{
    HelloEvent
};

class HeloListener implements ListenerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Redis
     */
    private $Redis;

    /**
     * @Inject()
     * @var Session
     */
    private  $Session;


    private $Container;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('sql');
        $this->Redis = $container->get(\Redis::class);
        $this->Container = $container;
    }

    public function listen(): array
    {
        return [
            HelloEvent::class
        ];
    }

    /**
     * @param QueryExecuted $event
     */
    public function process(object $Event)
    {
        $msg = $Event->msg;
        $fd = $Event->fd;
        $dir = getDirectiveByMsg($msg);
        // 打招呼应答
        if ($this->Container->get(Session::class)->getStatusByFd($fd) === 'int') {
            if (!in_array($dir, ['EHLO', 'HELO'])) {
                throw new SmtpBaseException([
                    'msg' => 'Error: send HELO/EHLO first',
                    'code' => 503
                ]);
            }
            if (!preg_match('/^(:?HELO)|(:?EHLO)\s+\w+/', $msg)) {
                throw new SmtpBadSyntxException();
            } else {
                $Session = $this->Container->get(Session::class);
                $Session->set($fd, 'status', 'HELO');

            }
        }

        if (in_array($dir, ['EHLO', 'HELO'])) {
            $Event->reply = smtp_pack("250 OK");
        }
    }
}
