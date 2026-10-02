<?php

declare(strict_types=1);

final class support_model extends model
{
    private const TABLES = ['support_tickets','support_replies','support_events'];

    public function databaseState(): string
    {
        foreach (self::TABLES as $table) { if (!$this->tableExists($table)) { return 'missing'; } }
        return 'ready';
    }

    public function installSchema(): void
    {
        $file=__DIR__.'/../sql/schema.sql';
        if(!is_file($file)){throw new RuntimeException('Support schema file was not found.');}
        $sql=file_get_contents($file);
        if($sql===false||trim($sql)===''){throw new RuntimeException('Support schema file is empty or unreadable.');}
        $statements=preg_split('/;\s*(?:\r?\n|$)/',$sql);
        if(!is_array($statements)){throw new RuntimeException('Support schema could not be parsed.');}
        foreach($statements as $statement){$statement=trim($statement);if($statement!==''){$this->query($statement);}}
    }

    public function deleteData(): void
    {
        $this->query('DELETE FROM `support_events`');
        $this->query('DELETE FROM `support_replies`');
        $this->query('DELETE FROM `support_tickets`');
    }

    public function getConfig(): array
    {
        $file=__DIR__.'/../data/support.json';
        if(!is_file($file)){return $this->defaultConfig();}
        $raw=file_get_contents($file);$config=is_string($raw)?json_decode($raw,true):null;
        return is_array($config)?$config:$this->defaultConfig();
    }

    public function saveConfig(array $config): void
    {
        $directory=__DIR__.'/../data';
        if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory)){throw new RuntimeException('Support data directory could not be created.');}
        $json=json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        if(!is_string($json)){throw new RuntimeException('Support configuration could not be encoded.');}
        if(file_put_contents($directory.'/support.json',$json.PHP_EOL,LOCK_EX)===false){throw new RuntimeException('Support configuration could not be written.');}
    }

    public function getTopics(bool $enabledOnly=false): array
    {
        $topics=$this->getConfig()['topics']??[];if(!is_array($topics)){return [];}$result=[];
        foreach($topics as $topic){if(!is_array($topic)){continue;}$value=strtolower(trim((string)($topic['value']??'')));$label=trim((string)($topic['label']??''));$enabled=(bool)($topic['enabled']??false);if($value===''||$label===''||($enabledOnly&&!$enabled)){continue;}$result[]=['value'=>$value,'label'=>$label,'enabled'=>$enabled];}
        return $result;
    }

    public function createTicket(array $data): string
    {
        $ticketId=$this->generateTicketId();
        $this->query('INSERT INTO support_tickets (ticket_id,email,name,type,subject,description,status,tier) VALUES (:ticket_id,:email,:name,:type,:subject,:description,:status,:tier)',[':ticket_id'=>$ticketId,':email'=>$data['email'],':name'=>$data['name'],':type'=>$data['type'],':subject'=>$data['subject'],':description'=>$data['description'],':status'=>'open',':tier'=>'t1']);
        $ticket=$this->findTicket($ticketId);if($ticket!==null){$this->addEvent((int)$ticket['id'],'created',null,'open',$data['name'],'Support ticket created.');}
        return $ticketId;
    }

    public function findTicket(string $ticketId): ?array
    {
        $ticket=$this->fetch('SELECT * FROM support_tickets WHERE ticket_id = :ticket_id LIMIT 1',[':ticket_id'=>strtolower($ticketId)]);return is_array($ticket)?$ticket:null;
    }

    public function getTickets(): array
    {
        $tickets=$this->fetchAll("SELECT * FROM support_tickets ORDER BY CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'waiting' THEN 3 WHEN 'resolved' THEN 4 WHEN 'closed' THEN 5 ELSE 6 END, created_at DESC");return is_array($tickets)?$tickets:[];
    }

    public function getReplies(int $ticketId): array {$rows=$this->fetchAll('SELECT * FROM support_replies WHERE ticket_id = :ticket_id ORDER BY created_at ASC, id ASC',[':ticket_id'=>$ticketId]);return is_array($rows)?$rows:[];}
    public function getPublicReplies(int $ticketId): array {$rows=$this->fetchAll('SELECT * FROM support_replies WHERE ticket_id = :ticket_id AND is_internal = 0 ORDER BY created_at ASC, id ASC',[':ticket_id'=>$ticketId]);return is_array($rows)?$rows:[];}
    public function getEvents(int $ticketId): array {$rows=$this->fetchAll('SELECT * FROM support_events WHERE ticket_id = :ticket_id ORDER BY created_at ASC, id ASC',[':ticket_id'=>$ticketId]);return is_array($rows)?$rows:[];}

    public function addReply(int $ticketId,string $author,string $message,bool $internal=false): void
    {$this->query('INSERT INTO support_replies (ticket_id,author,message,is_internal) VALUES (:ticket_id,:author,:message,:is_internal)',[':ticket_id'=>$ticketId,':author'=>$author,':message'=>$message,':is_internal'=>$internal?1:0]);$this->addEvent($ticketId,$internal?'internal_note':'reply',null,null,$author,null);}

    public function setStatus(array $ticket,string $status,string $actor): void
    {$closedAt=$status==='closed'?date('Y-m-d H:i:s'):null;$this->query('UPDATE support_tickets SET status = :status, closed_at = :closed_at WHERE id = :id',[':status'=>$status,':closed_at'=>$closedAt,':id'=>(int)$ticket['id']]);$this->addEvent((int)$ticket['id'],'status',(string)$ticket['status'],$status,$actor,null);}

    public function setTier(array $ticket,string $tier,string $actor,?string $note=null): void
    {$this->query('UPDATE support_tickets SET tier = :tier WHERE id = :id',[':tier'=>$tier,':id'=>(int)$ticket['id']]);$this->addEvent((int)$ticket['id'],'tier',(string)$ticket['tier'],$tier,$actor,$note);}

    public function assignTicket(array $ticket,?string $assignee,string $actor): void
    {$this->query('UPDATE support_tickets SET assigned_to = :assigned_to WHERE id = :id',[':assigned_to'=>$assignee,':id'=>(int)$ticket['id']]);$this->addEvent((int)$ticket['id'],'assignment',isset($ticket['assigned_to'])?(string)$ticket['assigned_to']:null,$assignee,$actor,null);}

    private function defaultConfig(): array
    {return ['version'=>'1','mail'=>['from_name'=>'','from_email'=>''],'slack'=>['enabled'=>false,'webhook_url'=>''],'topics'=>[['value'=>'general','label'=>'General','enabled'=>true]]];}

    private function addEvent(int $ticketId,string $eventType,?string $fromValue,?string $toValue,?string $actor,?string $note): void
    {$this->query('INSERT INTO support_events (ticket_id,event_type,from_value,to_value,actor,note) VALUES (:ticket_id,:event_type,:from_value,:to_value,:actor,:note)',[':ticket_id'=>$ticketId,':event_type'=>$eventType,':from_value'=>$fromValue,':to_value'=>$toValue,':actor'=>$actor,':note'=>$note]);}

    private function tableExists(string $table): bool
    {return (bool)$this->fetch('SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table_name LIMIT 1',['schema'=>DB_NAME,'table_name'=>$table]);}

    private function generateTicketId(): string
    {do{$ticketId=bin2hex(random_bytes(3));}while($this->findTicket($ticketId)!==null);return $ticketId;}
}
