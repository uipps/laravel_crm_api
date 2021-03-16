<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;

class GlobalLanguage
{
    /**
     * @var Country|null
     */
    private $country;

    /**
     * @var Currency|null
     */
    private $currency;

    /**
     * @var Language|null
     */
    private $language;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->loadLocale($request);

        // $request->merge([
        //     'country' => $this->country,
        //     'currency' => $this->currency,
        //     'language' => $this->language,
        // ]);

        return $next($request);
    }

    /**
     * 加载框架的语言包
     */
    private function loadLocale(Request $request)
    {
        $lang_code = $request->input('locale', $request->header('locale')); // 默认使用header头信息中的locale
        $lang_code = str_replace('-','_', $lang_code);
        
        if($lang_code){
            App::setLocale($lang_code);
        }

    }
}
