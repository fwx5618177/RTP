use App\Gateway\JanusGateway;

return [
// ... 其他配置
JanusGateway::class => function(ContainerInterface $container) {
return new JanusGateway();
},
// ... 其他配置
];