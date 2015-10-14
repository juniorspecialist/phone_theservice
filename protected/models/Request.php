<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 27.08.14
 * Time: 9:52
 */

/*
 * обработка параметров для определения города по IP и номера телефона
 */
class Request extends CFormModel {

    //ip-адрес пользователя
    public $ip;

    //шаблон страницы на которую заходит юзер(нужен для учитывания какого номера телефона отдавать юзеру, если есть их несколько подвязанных к шаблонам)
    public $template = 1;

    public $city;//город котор. определили по IP пользователя

    public $city_msk = 68;//ID города Москва в БД

    public $phones = array();//массив номеров для отображения(формат:город-номер)

    public $regions = array();//список регион. городов(активных)+их номера по дефолту

    public $site_id = 1;//сайт по которому получаем список городов-номеров(по умолчанию theservice.ru=1)

    /*
     * всегда данные по сайту указаны для города москва, можем их найти для указанного сайта
     */
    public function getMoscow(){

        //сперва определим ID города - Москва
        $moscow_row = City::findTownByName('Москва');

        $sql = 'SELECT * FROM {{city_site_phone}} WHERE city_id=:city_id AND site_id=:site_id';

        $query = YiiBase::app()->db->cache(3600)->createCommand($sql);

        $query->bindValue(':city_id', $moscow_row['id'], PDO::PARAM_INT);

        $query->bindValue(':site_id', $this->site_id, PDO::PARAM_INT);

        return $query->queryRow();
    }


    /*
     * поиск активных регионов
    */
    public function findActiveRegions(){

        //получаем список активных регионов для списка
        $sql = 'SELECT tbl_city_site_phone.phone, tbl_city.city AS city
                FROM tbl_city_site_phone
                LEFT JOIN tbl_city ON tbl_city_site_phone.city_id = tbl_city.id
                WHERE tbl_city_site_phone.site_id=:site_id AND tbl_city_site_phone.active =:active AND main_city =:main_city
                ORDER BY (city =  "Москва") DESC , (city =  "Санкт-Петербург") DESC , city ASC';

        $query = YiiBase::app()->db->cache(3600)->createCommand($sql);

        $query->bindValue(':main_city', City::ACTIVE,PDO::PARAM_INT);
        $query->bindValue(':active', City::ACTIVE,PDO::PARAM_INT);
        $query->bindValue(':site_id', $this->site_id,PDO::PARAM_INT);

        return $query->queryAll();
    }

    /*
     * основная логика работы:определяем город, опрпеделяем номера телефонов
     */
    public function action(){

        if($this->hasErrors()){
            echo '<pre>'; print_r($this->errors);
        }else{
            //определяем город юзера по его IP
            $this->city = YiiBase::app()->sypexgeo->action($this->ip);

            //не удалось определить город по IP юзера
            if(empty($this->city)){
                //тогда выбираем город-Москва и отдаём данные по этому городу
                $city = $this->getMoscow();
            }else{
                //поиск города в справочнике
                $city_row = City::findTownByName($this->city);

                //Не нашли город в справочнике
                if(empty($city_row)){
                    //не удалось найти город в нашем списке - тогда город - Москва для этого сайта
                    $city = $this->getMoscow();
                }else{
                    //поиск соответствия = город+сайт+активность
                    $query = YiiBase::app()->db->cache(3600)->createCommand('SELECT * FROM {{city_site_phone}} WHERE city_id=:city_id AND site_id=:site_id AND active=1');
                    $query->bindValue(':city_id', $city_row['id'], PDO::PARAM_INT);
                    $query->bindValue(':site_id', $this->site_id, PDO::PARAM_INT);

                    $city = $query->queryRow();

                    //не нашли совпадения по АКТИВНОСТИ_города+совпадению_по_сайту+названию_города
                    if(empty($city)){
                        $city = $this->getMoscow();
                        $this->phones[$city['phone']] = 'Москва';
                    }
                }
            }

            //формируем список активных регионов по сайту, для отображения
            if(empty($this->regions)){
                $this->regions = CHtml::listData($this->findActiveRegions(), 'city','phone');
            }

            //если город - АКТИВЕН - используем его телефон
            if(!empty($city) && empty($this->phones)){

                //получим массив всей информаци о городе
                $row_info_city = City::findById($city['city_id']);

                //определяем сколько строчек телефонов будет
                if($row_info_city['main_city']==City::NOT_ACTIVE){//(3 номера)
                    //добавили номер города
                    $this->phones[$city['phone']] = $row_info_city['city'];
                }
                ////если есть привязки к шаблонам - ищем телефон подвязанный к шаблону(если НЕ находим то возвращаем по дефолту номер)
                $phone_from_template = Phone::getPhoneByTemplateANDCity($this->template, $row_info_city['parent_id'], $this->site_id);

                $region_city_name = City::findNameTownById($row_info_city['parent_id']);

                // нашли номер телефона подвязанного к шаблону
                if(!empty($phone_from_template)){
                    $this->phones[$phone_from_template] = $region_city_name;
                    $this->regions[$region_city_name] = $phone_from_template;

                }else{

                    $query_region = YiiBase::app()->db->createCommand('SELECT phone FROM {{city_site_phone}} WHERE city_id=:city_id AND site_id=:site_id');
                    $query_region->bindValue(':site_id', $this->site_id, PDO::PARAM_INT);
                    $query_region->bindValue(':city_id', $row_info_city['parent_id'], PDO::PARAM_INT);
                    $region_default_phone = $query_region->queryScalar();

                    //используем номер телефона региона по-умолчанию
                    $this->phones[$region_default_phone] = $region_city_name;
                }

            }
        }
    }

    public function rules()
    {
        return array(
            array('ip, template, site_id', 'required'),
        );
    }
} 