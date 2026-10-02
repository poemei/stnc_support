<?php

declare(strict_types=1);

final class slack_notifier
{
    public function send(string $webhookUrl, string $message): bool
    {
        $webhookUrl=trim($webhookUrl);
        if($webhookUrl==='' || filter_var($webhookUrl,FILTER_VALIDATE_URL)===false){return false;}
        $payload=json_encode(['text'=>$message],JSON_UNESCAPED_SLASHES);
        if(!is_string($payload)){return false;}
        $context=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nConnection: close\r\n",'content'=>$payload,'timeout'=>5,'ignore_errors'=>true]]);
        try{
            $result=@file_get_contents($webhookUrl,false,$context);
            if($result===false){error_log('[STNC Support] Slack notification failed.');return false;}
            return trim($result)==='ok';
        }catch(Throwable $e){
            error_log('[STNC Support] Slack notification failed: '.$e->getMessage());
            return false;
        }
    }
}
