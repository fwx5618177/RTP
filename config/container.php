use App\Gateway\JanusGateway;
use App\Services\AsteriskService;
use App\Controllers\PbxController;
use Psr\Container\ContainerInterface;

return [
// ... 其他配置
JanusGateway::class => function(ContainerInterface $container) {
return new JanusGateway();
},

// Asterisk Service
AsteriskService::class => function(ContainerInterface $container) {
return new AsteriskService();
},

// PBX Controller
PbxController::class => function(ContainerInterface $container) {
return new PbxController(
$container->get(AsteriskService::class)
);
},
// ... 其他配置
];