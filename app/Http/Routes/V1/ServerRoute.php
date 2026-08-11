<?php
namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class ServerRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'server'
        ], function ($router) {
            $router->any('/{class}/{action}', function($class, $action) {
                // 类白名单 + 仅允许控制器自身声明的方法，收窄反射调用面
                // 白名单比较大小写不敏感：节点端（v2node 等）历史上以 UniProxy 等大写开头形式调用，必须兼容
                $allowedClasses = ['uniproxy', 'deepbwork', 'shadowsockstidalab', 'trojantidalab'];
                if (!in_array(strtolower($class), $allowedClasses, true)) {
                    abort(404);
                }
                // 统一为不带前导反斜杠的类名：ReflectionClass::getName() 返回值不带前导反斜杠，
                // 比较前必须规范化，否则声明类检查会误拦全部请求
                $controllerClass = ltrim("\\App\\Http\\Controllers\\V1\\Server\\" . ucfirst($class) . "Controller", "\\");
                if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
                    abort(404);
                }
                $reflection = new \ReflectionMethod($controllerClass, $action);
                if ($reflection->getDeclaringClass()->getName() !== $controllerClass) {
                    abort(404);
                }
                $ctrl = \App::make($controllerClass);
                return \App::call([$ctrl, $action]);
            });
        });
    }
}
