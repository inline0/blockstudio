<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BlockstudioVendor\Symfony\Component\VarDumper\Cloner;

use BlockstudioVendor\Symfony\Component\VarDumper\Caster\Caster;
use BlockstudioVendor\Symfony\Component\VarDumper\Exception\ThrowingCasterException;
/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static array $defaultCasters = ['__PHP_Incomplete_Class' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\Caster', 'castPhpIncompleteClass'], 'AddressInfo' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\AddressInfoCaster', 'castAddressInfo'], 'Socket' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SocketCaster', 'castSocket'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\CutStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'castStub'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\CutArrayStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'castCutArray'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\ConstStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'castStub'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\EnumStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'castEnum'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\ScalarStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'castScalar'], 'Fiber' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\FiberCaster', 'castFiber'], 'Closure' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castClosure'], 'Generator' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castGenerator'], 'ReflectionType' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castType'], 'ReflectionAttribute' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castAttribute'], 'ReflectionGenerator' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castReflectionGenerator'], 'ReflectionClass' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castClass'], 'ReflectionClassConstant' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castClassConstant'], 'ReflectionFunctionAbstract' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castFunctionAbstract'], 'ReflectionMethod' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castMethod'], 'ReflectionParameter' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castParameter'], 'ReflectionProperty' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castProperty'], 'ReflectionReference' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castReference'], 'ReflectionExtension' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castExtension'], 'ReflectionZendExtension' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castZendExtension'], 'BlockstudioVendor\Doctrine\Common\Persistence\ObjectManager' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\Doctrine\Common\Proxy\Proxy' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DoctrineCaster', 'castCommonProxy'], 'BlockstudioVendor\Doctrine\ORM\Proxy\Proxy' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DoctrineCaster', 'castOrmProxy'], 'BlockstudioVendor\Doctrine\ORM\PersistentCollection' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DoctrineCaster', 'castPersistentCollection'], 'BlockstudioVendor\Doctrine\Persistence\ObjectManager' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'DOMException' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castException'], 'BlockstudioVendor\Dom\Exception' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castException'], 'DOMStringList' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNameList' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMImplementation' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castImplementation'], 'BlockstudioVendor\Dom\Implementation' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castImplementation'], 'DOMImplementationList' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNode' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'BlockstudioVendor\Dom\Node' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNameSpaceNode' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMDocument' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDocument'], 'BlockstudioVendor\Dom\XMLDocument' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castXMLDocument'], 'BlockstudioVendor\Dom\HTMLDocument' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castHTMLDocument'], 'DOMNodeList' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'BlockstudioVendor\Dom\NodeList' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNamedNodeMap' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'BlockstudioVendor\Dom\DTDNamedNodeMap' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMXPath' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'BlockstudioVendor\Dom\XPath' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'BlockstudioVendor\Dom\HTMLCollection' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'BlockstudioVendor\Dom\TokenList' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'XMLReader' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\XmlReaderCaster', 'castXmlReader'], 'ErrorException' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castErrorException'], 'Exception' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castException'], 'Error' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castError'], 'BlockstudioVendor\Symfony\Bridge\Monolog\Logger' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\Symfony\Component\DependencyInjection\ContainerInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\Symfony\Component\EventDispatcher\EventDispatcherInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\Symfony\Component\HttpClient\AmpHttpClient' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClient'], 'BlockstudioVendor\Symfony\Component\HttpClient\CurlHttpClient' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClient'], 'BlockstudioVendor\Symfony\Component\HttpClient\NativeHttpClient' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClient'], 'BlockstudioVendor\Symfony\Component\HttpClient\Response\AmpResponse' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'BlockstudioVendor\Symfony\Component\HttpClient\Response\AmpResponseV4' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'BlockstudioVendor\Symfony\Component\HttpClient\Response\AmpResponseV5' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'BlockstudioVendor\Symfony\Component\HttpClient\Response\CurlResponse' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'BlockstudioVendor\Symfony\Component\HttpClient\Response\NativeResponse' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'BlockstudioVendor\Symfony\Component\HttpFoundation\Request' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castRequest'], 'BlockstudioVendor\Symfony\Component\Uid\Ulid' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castUlid'], 'BlockstudioVendor\Symfony\Component\Uid\Uuid' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castUuid'], 'BlockstudioVendor\Symfony\Component\VarExporter\Internal\LazyObjectState' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castLazyObjectState'], 'BlockstudioVendor\Symfony\Component\VarDumper\Exception\ThrowingCasterException' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castThrowingCasterException'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\TraceStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castTraceStub'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\FrameStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castFrameStub'], 'BlockstudioVendor\Symfony\Component\VarDumper\Cloner\AbstractCloner' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\Symfony\Component\ErrorHandler\Exception\FlattenException' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castFlattenException'], 'BlockstudioVendor\Symfony\Component\ErrorHandler\Exception\SilencedErrorContext' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castSilencedErrorContext'], 'BlockstudioVendor\Imagine\Image\ImageInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ImagineCaster', 'castImage'], 'BlockstudioVendor\Ramsey\Uuid\UuidInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\UuidCaster', 'castRamseyUuid'], 'BlockstudioVendor\ProxyManager\Proxy\ProxyInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ProxyManagerCaster', 'castProxy'], 'PHPUnit_Framework_MockObject_MockObject' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\PHPUnit\Framework\MockObject\MockObject' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\PHPUnit\Framework\MockObject\Stub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\Prophecy\Prophecy\ProphecySubjectInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'BlockstudioVendor\Mockery\MockInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'PDO' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\PdoCaster', 'castPdo'], 'PDOStatement' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\PdoCaster', 'castPdoStatement'], 'AMQPConnection' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castConnection'], 'AMQPChannel' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castChannel'], 'AMQPQueue' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castQueue'], 'AMQPExchange' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castExchange'], 'AMQPEnvelope' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castEnvelope'], 'ArrayObject' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castArrayObject'], 'ArrayIterator' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castArrayIterator'], 'SplDoublyLinkedList' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castDoublyLinkedList'], 'SplFileInfo' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castFileInfo'], 'SplFileObject' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castFileObject'], 'SplHeap' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castHeap'], 'SplObjectStorage' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castObjectStorage'], 'SplPriorityQueue' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castHeap'], 'OuterIterator' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castOuterIterator'], 'WeakMap' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castWeakMap'], 'WeakReference' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SplCaster', 'castWeakReference'], 'Redis' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedis'], 'BlockstudioVendor\Relay\Relay' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedis'], 'RedisArray' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedisArray'], 'RedisCluster' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedisCluster'], 'DateTimeInterface' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DateCaster', 'castDateTime'], 'DateInterval' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DateCaster', 'castInterval'], 'DateTimeZone' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DateCaster', 'castTimeZone'], 'DatePeriod' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DateCaster', 'castPeriod'], 'GMP' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\GmpCaster', 'castGmp'], 'MessageFormatter' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\IntlCaster', 'castMessageFormatter'], 'NumberFormatter' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\IntlCaster', 'castNumberFormatter'], 'IntlTimeZone' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\IntlCaster', 'castIntlTimeZone'], 'IntlCalendar' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\IntlCaster', 'castIntlCalendar'], 'IntlDateFormatter' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\IntlCaster', 'castIntlDateFormatter'], 'Memcached' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\MemcachedCaster', 'castMemcached'], 'BlockstudioVendor\Ds\Collection' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DsCaster', 'castCollection'], 'BlockstudioVendor\Ds\Map' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DsCaster', 'castMap'], 'BlockstudioVendor\Ds\Pair' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DsCaster', 'castPair'], 'BlockstudioVendor\Symfony\Component\VarDumper\Caster\DsPairStub' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\DsCaster', 'castPairStub'], 'mysqli_driver' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\MysqliCaster', 'castMysqliDriver'], 'CurlHandle' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\CurlCaster', 'castCurl'], 'BlockstudioVendor\Dba\Connection' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castDba'], ':dba' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castDba'], ':dba persistent' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castDba'], 'GdImage' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\GdCaster', 'castGd'], 'SQLite3Result' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\SqliteCaster', 'castSqlite3Result'], 'BlockstudioVendor\PgSql\Lob' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\PgSqlCaster', 'castLargeObject'], 'BlockstudioVendor\PgSql\Connection' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\PgSqlCaster', 'castLink'], 'BlockstudioVendor\PgSql\Result' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\PgSqlCaster', 'castResult'], ':process' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castProcess'], ':stream' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castStream'], 'OpenSSLAsymmetricKey' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\OpenSSLCaster', 'castOpensslAsymmetricKey'], 'OpenSSLCertificateSigningRequest' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\OpenSSLCaster', 'castOpensslCsr'], 'OpenSSLCertificate' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\OpenSSLCaster', 'castOpensslX509'], ':persistent stream' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castStream'], ':stream-context' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castStreamContext'], 'XmlParser' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\XmlResourceCaster', 'castXml'], 'RdKafka' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castRdKafka'], 'BlockstudioVendor\RdKafka\Conf' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castConf'], 'BlockstudioVendor\RdKafka\KafkaConsumer' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castKafkaConsumer'], 'BlockstudioVendor\RdKafka\Metadata\Broker' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castBrokerMetadata'], 'BlockstudioVendor\RdKafka\Metadata\Collection' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castCollectionMetadata'], 'BlockstudioVendor\RdKafka\Metadata\Partition' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castPartitionMetadata'], 'BlockstudioVendor\RdKafka\Metadata\Topic' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopicMetadata'], 'BlockstudioVendor\RdKafka\Message' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castMessage'], 'BlockstudioVendor\RdKafka\Topic' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopic'], 'BlockstudioVendor\RdKafka\TopicPartition' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopicPartition'], 'BlockstudioVendor\RdKafka\TopicConf' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopicConf'], 'BlockstudioVendor\FFI\CData' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\FFICaster', 'castCTypeOrCData'], 'BlockstudioVendor\FFI\CType' => ['BlockstudioVendor\Symfony\Component\VarDumper\Caster\FFICaster', 'castCTypeOrCData']];
    protected int $maxItems = 2500;
    protected int $maxString = -1;
    protected int $minDepth = 1;
    /**
     * @var array<string, list<callable>>
     */
    private array $casters = [];
    /**
     * @var callable|null
     */
    private $prevErrorHandler;
    private array $classInfo = [];
    private int $filter = 0;
    /**
     * @param callable[]|null $casters A map of casters
     *
     * @see addCasters
     */
    public function __construct(?array $casters = null)
    {
        $this->addCasters($casters ?? static::$defaultCasters);
    }
    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or object types to a callback.
     * Use types as keys and callable casters as values.
     * Prefix types with `::`,
     * see e.g. self::$defaultCasters.
     *
     * @param array<string, callable> $casters A map of casters
     */
    public function addCasters(array $casters): void
    {
        foreach ($casters as $type => $callback) {
            $this->casters[$type][] = $callback;
        }
    }
    /**
     * Adds default casters for resources and objects.
     *
     * Maps resources or object types to a callback.
     * Use types as keys and callable casters as values.
     * Prefix types with `::`,
     * see e.g. self::$defaultCasters.
     *
     * @param array<string, callable> $casters A map of casters
     */
    public static function addDefaultCasters(array $casters): void
    {
        self::$defaultCasters = [...self::$defaultCasters, ...$casters];
    }
    /**
     * Sets the maximum number of items to clone past the minimum depth in nested structures.
     */
    public function setMaxItems(int $maxItems): void
    {
        $this->maxItems = $maxItems;
    }
    /**
     * Sets the maximum cloned length for strings.
     */
    public function setMaxString(int $maxString): void
    {
        $this->maxString = $maxString;
    }
    /**
     * Sets the minimum tree depth where we are guaranteed to clone all the items.  After this
     * depth is reached, only setMaxItems items will be cloned.
     */
    public function setMinDepth(int $minDepth): void
    {
        $this->minDepth = $minDepth;
    }
    /**
     * Clones a PHP variable.
     *
     * @param int $filter A bit field of Caster::EXCLUDE_* constants
     */
    public function cloneVar(mixed $var, int $filter = 0): Data
    {
        $this->prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) {
            if (\E_RECOVERABLE_ERROR === $type || \E_USER_ERROR === $type) {
                // Cloner never dies
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }
            if ($this->prevErrorHandler) {
                return ($this->prevErrorHandler)($type, $msg, $file, $line, $context);
            }
            return \false;
        });
        $this->filter = $filter;
        if ($gc = gc_enabled()) {
            gc_disable();
        }
        try {
            return new Data($this->doClone($var));
        } finally {
            if ($gc) {
                gc_enable();
            }
            restore_error_handler();
            $this->prevErrorHandler = null;
        }
    }
    /**
     * Effectively clones the PHP variable.
     */
    abstract protected function doClone(mixed $var): array;
    /**
     * Casts an object to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     */
    protected function castObject(Stub $stub, bool $isNested): array
    {
        $obj = $stub->value;
        $class = $stub->class;
        if (str_contains($class, "@anonymous\x00")) {
            $stub->class = get_debug_type($obj);
        }
        if (isset($this->classInfo[$class])) {
            [$i, $parents, $hasDebugInfo, $fileInfo] = $this->classInfo[$class];
        } else {
            $i = 2;
            $parents = [$class];
            $hasDebugInfo = method_exists($class, '__debugInfo');
            foreach (class_parents($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            foreach (class_implements($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            $parents[] = '*';
            $r = new \ReflectionClass($class);
            $fileInfo = $r->isInternal() || $r->isSubclassOf(Stub::class) ? [] : ['file' => $r->getFileName(), 'line' => $r->getStartLine()];
            $this->classInfo[$class] = [$i, $parents, $hasDebugInfo, $fileInfo];
        }
        $stub->attr += $fileInfo;
        $a = Caster::castObject($obj, $class, $hasDebugInfo, $stub->class);
        try {
            while ($i--) {
                if (!empty($this->casters[$p = $parents[$i]])) {
                    foreach ($this->casters[$p] as $callback) {
                        $a = $callback($obj, $a, $stub, $isNested, $this->filter);
                    }
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '') . '⚠' => new ThrowingCasterException($e)] + $a;
        }
        return $a;
    }
    /**
     * Casts a resource to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     */
    protected function castResource(Stub $stub, bool $isNested): array
    {
        $a = [];
        $res = $stub->value;
        $type = $stub->class;
        try {
            if (!empty($this->casters[':' . $type])) {
                foreach ($this->casters[':' . $type] as $callback) {
                    $a = $callback($res, $a, $stub, $isNested, $this->filter);
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '') . '⚠' => new ThrowingCasterException($e)] + $a;
        }
        return $a;
    }
}
