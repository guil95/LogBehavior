<?php

namespace App\Model\Behavior;


use Cake\Controller\Component\AuthComponent;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use Cake\ORM\TableRegistry;
use Cake\Test\Fixture\CakeSessionsFixture;
use Cake\Utility\Security;
use Firebase\JWT\JWT;


class LogBehavior extends Behavior {
    use DataHoraFilialTrait;

    private $nome_tabela;
    private $json_depois;
    private $json_antes;
    private $idusuario;
    private $idregistro;

    public function afterSave(Event $event, EntityInterface $entity)
    {
        $tabela = $event->getSubject();
        $idtablename = $tabela->getPrimaryKey();

        $propriedades = array_keys($entity->toArray());
        $this->setAntesDepois($propriedades, $entity);

        $headers = getallheaders();
        $this->setUserId($headers);

        $this->nome_tabela = $this->getConfig('tableName');
        $this->idregistro = $entity->$idtablename;

        $data = $this->prepareParams2Save();
        
        $this->saveLog($data);
    }

    private function setUserId($headers)
    {
        $headers = isset($headers['Authorization']) ? $headers['Authorization'] : $headers['authorization'];
        $token = substr($headers,7);

        $decode = JWT::decode($token,Security::getSalt(), array('HS256'));

        $this->idusuario = $decode->sub->id;
    }

    private function setAntesDepois($propriedades, $entity)
    {

        if(!$entity->isNew()){
             $propriedadesAlteradasAntes = $entity->extractOriginalChanged($propriedades);
             $propriedadesAlteradasDepois = $entity->toArray();
             $arr_depois = [];
            foreach ($propriedadesAlteradasDepois as $keyD => $propriedadeAlteradaDepois) {
                foreach ($propriedadesAlteradasAntes as $keyA => $propriedadeAlteradaAntes){
    
                    if($keyA != $keyD){
                        continue;
                    }
    
                    $arr_depois[$keyD] = $propriedadesAlteradasDepois[$keyD];
                }
            }
            $this->json_depois = json_encode($arr_depois);
            $this->json_antes = json_encode($propriedadesAlteradasAntes);
        }else{
            $this->json_depois = json_encode($entity->toArray());
            $this->json_antes =  null;
        }


    }

    private function prepareParams2Save()
    {
        return [
            'nome_tabela' => $this->nome_tabela,
            'json_antes' => $this->json_antes,
            'json_depois' => $this->json_depois,
            'idusuario' => $this->idusuario,
            'data_operacao' => date('d/m/Y H:i:s',strtotime('now')),
            'idregistro' => $this->idregistro,
        ];
        
    }
    
    private function saveLog($data)
    {
        $logTable = TableRegistry::get('Logs');
        $logsEntity = $logTable->newEntity($data);

        if (!$logTable->save($logsEntity)) {
            Log::debug('ERRO AO SALVAR LOG '.$logsEntity->getErrors());
        }
    }
}
