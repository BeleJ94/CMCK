<?php

final class DagrilIntegrationTest
{
    private static $db;
    private static $checks=0;
    private static $failures=[];

    public static function boot()
    {
        if(PHP_SAPI==='cli'&&ob_get_level()===0){ob_start();}
        ini_set('session.save_path',sys_get_temp_dir());
        $root=dirname(__DIR__,2);
        require_once $root.'/app/helpers/functions.php';
        require_once $root.'/app/core/Database.php';
        require_once $root.'/app/core/Model.php';
        require_once $root.'/app/core/Auth.php';
        require_once $root.'/app/services/AuthorizationService.php';
        self::$db=Database::getInstance()->connection();
        $configured=(string)config('database.database');
        $actual=(string)self::$db->query('SELECT DATABASE()')->fetchColumn();
        if($configured!==$actual||!preg_match('/_(test|testing)$/i',$actual)){
            throw new RuntimeException('GARDE-FOU: base de test obligatoire; connexion refusée sur '.$actual.'.');
        }
        if(strcasecmp($actual,'cmck_milltrack')===0){throw new RuntimeException('GARDE-FOU: base principale interdite.');}
        $_SERVER['HTTP_USER_AGENT']='DagrilIntegrationSuite';
        Auth::start();
        return self::$db;
    }

    public static function db(){return self::$db;}
    public static function check($condition,$message){self::$checks++;echo($condition?'[OK] ':'[ECHEC] ').$message.PHP_EOL;if(!$condition)self::$failures[]=$message;}
    public static function expectException($callback,$message){$caught=false;try{$callback();}catch(Exception$e){$caught=true;}self::check($caught,$message);}
    public static function scalar($sql,$params=[]){$q=self::$db->prepare($sql);$q->execute($params);return$q->fetchColumn();}
    public static function row($sql,$params=[]){$q=self::$db->prepare($sql);$q->execute($params);return$q->fetch();}
    public static function loginRole($slug,$site=null){$u=self::row("SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='active' AND u.deleted_at IS NULL AND r.slug=? ORDER BY u.id LIMIT 1",[$slug]);if(!$u)throw new RuntimeException('Utilisateur '.$slug.' requis.');Auth::login($u);if($site!==null)Auth::selectSite((string)$site);return Auth::user();}
    public static function finish(){echo PHP_EOL.self::$checks.' vérification(s), '.count(self::$failures).' échec(s).'.PHP_EOL;if(PHP_SAPI==='cli'&&ob_get_level()>0)ob_end_flush();if(self::$failures)exit(1);}
}
