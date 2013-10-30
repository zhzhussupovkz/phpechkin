<?php

/**
* @author zhzhussupovkz@gmail.com
*/
class PHPechkin {

	//login for auth
	private $username;

	//password for auth
	private $password;

	//pechkin mail api url
	private $apiUrl = 'https://api.pechkin-mail.ru/';

	//check email
	private $checkUrl = 'http://pechkinfix.ru/check.php';

	//use curl
	public $useCURL = false;

	//constructor
	public function __construct($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}

	//get error codes
	private function getError($key) {
		$errors = array(
			'2' => 'Ошибка при добавлении в базу',
			'3' => 'Заданы не все необходимые параметры',
			'4' => 'Нет данных при выводе',
			'5' => 'У пользователя нет адресной базы с таким id',
			'6' => 'Некорректный email-адрес',
			'7' => 'Такой пользователь уже есть в этой адресной базе',
			'8' => 'Лимит по количеству активных подписчиков на тарифном плане клиента',
			'9' => 'Нет такого подписчика у клиента',
			'10' => 'Пользователь уже отписан',
			'11' => 'Нет данных для обновления подписчика',
			'12' => 'Не заданы элементы списка',
			'13' => 'Не задано время рассылки',
			'14' => 'Не задан заголовок письма',
			'15' => 'Не задано поле От Кого?',
			'16' => 'Не задан обратный адрес',
			'17' => 'Не задана ни html ни plain_text версия письма',
			'18' => 'Нет ссылки отписаться в тексте рассылки. Пример ссылки: отписаться',
			'19' => 'Нет ссылки отписаться в тексте рассылки',
			'20' => 'Задан недопустимый статус рассылки',
			'21' => 'Рассылка уже отправляется',
			'22' => 'У вас нет кампании с таким campaign_id',
			'23' => 'Нет такого поля для сортировки',
			'24' => 'Заданы недопустимые события для авторассылки',
			'25' => 'Загружаемый файл уже существует',
			'26' => 'Загружаемый файл больше 5 Мб',
			'27' => 'Файл не найден',
			'28' => 'Указанный шаблон не существует',
		);
		return $errors[$key];
	}

	//get data by method
	public function getData($method, $params = array()) {
		$user = array('username' => $this->username, 'password' => $this->password);
		$params = array_merge($user, $params);
		$params = http_build_query($params);

		$fileUrl = $this->apiUrl.'?method='.$method.'&'.$params.'&format=xml';

		if ($this->useCURL) {
			$options = array(
				CURLOPT_URL => $fileUrl,
				CURLOPT_POST => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => 1,
			);

			$curl = curl_init();
			curl_setopt_array($curl, $options);
			$result = curl_exec($curl);
			curl_close($curl);
		} else {
			try {
				$result = file_get_contents($fileUrl);
			} catch (Exception $e) {
				//do something
			}
		}

		$xml = simplexml_load_string($result);
		$json = json_encode($xml);
		$final = json_decode($json, TRUE);
		if ($final['msg']['err_code'] == '0') {
			return $final['data'];
		} else {
			return $this->getError($final['msg']['err_code']);
		}
	}

	//check email address
	public function checkEmail($email) {
		$checking = $this->checkUrl.'?email='.$email.'&format=xml';
		$result = file_get_contents($checking);
		$xml = simplexml_load_string($result);
		$json = json_encode($xml);
		$final = json_decode($json, TRUE);
		$err = $final['err_code'];
		if ($err == '0' || $err == '1') {
			return $final['text'];
		} else {
			return false;
		}
	}


	/*****************************************************************
	**************** Работа с Адресными Базами ***********************
	******************************************************************/
	//lists.get - Получаем список баз пользователя
	/*
	optional: list_id
	*/
	public function lists_get($list_id = '') {
		if (!empty($list_id))
			$params = array('list_id' => $list_id);
		else
			$params = array();
		return $this->getData('lists.get', $params);
	}

	//lists.add - Добавляем адресную базу
	/*
	required: name
	optional: abuse_email, abuse_name, company...
	http://pechkin-mail.ru/?page=api_details&method=lists.add
	*/
	public function lists_add($name, $params = array()) {
		$required = array('name' => $name);
		if (isset($params['abuse_email'])) {
			$email = $this->checkEmail($params['abuse_email']);
			if ($email !== false)
				$params['abuse_email'] = $email;
			else
				return $this->getError('6');
		}
		$params = array_merge($required, $params);
		return $this->getData('lists.add', $params);
	}

	//lists.update - Обновляем контактную информацию адресной базы
	/*
	required: list_id
	optional: name, abuse_email, abuse_name, company...
	see: http://pechkin-mail.ru/?page=api_details&method=lists.update
	*/
	public function lists_update($list_id, $params = array()) {
		$list_id = array('list_id' => $list_id);
		if (isset($params['abuse_email'])) {
			$email = $this->checkEmail($params['abuse_email']);
			if ($email !== false)
				$params['abuse_email'] = $email;
			else
				return $this->getError('6');
		}
		$params = array_merge($list_id, $params);
		return $this->getData('lists.update', $params);
	}

	//lists.delete - Удаляем адресную базу и всех активных подписчиков в ней.
	/*
	required: list_id
	*/
	public function lists_delete($list_id) {
		$params = array('list_id' => $list_id);
		return $this->getData('lists.delete', $params);
	}

	//lists.get_members - Получаем подписчиков в адресной базе с возможность фильтра и регулировки выдачи.
	/*
	required: list_id
	optional: state, start, limit...
	see: http://pechkin-mail.ru/?page=api_details&method=lists.get_members
	*/
	public function lists_get_members($list_id, $params = array()) {
		$required = array('list_id' => $list_id);
		$params = array_merge($required, $params);
		return $this->getData('lists.get_members', $params);
	}

	//lists.upload - Импорт подписчиков из файла
	/*
	required: list_id, file, email
	optional: merge_1, merge_2, type, update...
	see: http://pechkin-mail.ru/?page=api_details&method=lists.upload
	*/
	public function lists_upload($list_id, $file, $email, $params = array()) {
		$email = $this->checkEmail($email);
		if ($email !== false)
			$required = array('list_id' => $list_id, 'file' => $file, 'email' => $email);
		else
			return $this->getError('6');
		$params = array_merge($required, $params);
		return $this->getData('lists.upload', $params);
	}

	//lists.add_member - Добавляем подписчика в базу
	/*
	required: list_id, email
	optional: merge_1, merge_2..., update...
	see: http://pechkin-mail.ru/?page=api_details&method=lists.add_member
	*/
	public function lists_add_member($list_id, $email, $params = array()) {
		$email = $this->checkEmail($email);
		if ($email !== false)
			$required = array('list_id' => $list_id, 'email' => $email);
		else
			return $this->getError('6');
		$params = array_merge($required, $params);
		return $this->getData('lists.add_member', $params);
	}

	//lists.update_member - Редактируем подписчика в базе
	/*
	required: member_id
	optional: merge_1, merge_2...
	see: http://pechkin-mail.ru/?page=api_details&method=lists.update_member
	*/
	public function lists_update_member($member_id, $params = array()) {
		$required = array('member_id' => $member_id);
		$params = array_merge($required, $params);
		return $this->getData('lists.update_member', $params);
	}

	//lists.delete_member - Удаляем подписчика из базы
	/*
	required: member_id
	*/
	public function lists_delete_member($member_id) {
		$params = array('member_id' => $member_id);
		return $this->getData('lists.delete_member', $params);
	}

	//lists.unsubscribe_member - Редактируем подписчика в базе
	/*
	optional: member_id, email, list_id
	see: http://pechkin-mail.ru/?page=api_details&method=lists.unsubscribe_member
	*/
	public function lists_unsubscribe_member($params = array()) {
		if (isset($params['email'])) {
			$email = $this->checkEmail($params['email']);
			if ($email !== false)
				$params['email'] = $email;
			else
				return $this->getError('6');
		}
		return $this->getData('lists.unsubscribe_member', $params);
	}

	//lists.move_member - Перемещаем подписчика в другую адресную базу.
	/*
	required: member_id, list_id
	*/
	public function lists_move_member($member_id, $list_id) {
		$params = array('member_id' => $member_id, 'list_id' => $list_id);
		return $this->getData('lists.move_member', $params);
	}

	//lists.copy_member - Копируем подписчика в другую адресную базу
	/*
	required: member_id, list_id
	*/
	public function lists_copy_member($member_id, $list_id) {
		$params = array('member_id' => $member_id, 'list_id' => $list_id);
		return $this->getData('lists.copy_member', $params);
	}

	//lists.add_merge - Добавить дополнительное поле в адресную базу
	/*
	required: list_id, type
	optional: choises, title, ...
	see: http://pechkin-mail.ru/?page=api_details&method=lists.add_merge
	*/
	public function lists_add_merge($list_id, $type, $params = array()) {
		$required = array('list_id' => $list_id, 'type' => $type);
		$params = array_merge($required, $params);
		return $this->getData('lists.add_merge', $params);
	}

	//lists.update_merge - Обновить настройки дополнительного поля в адресной базе
	/*
	required: list_id, merge_id
	optional: choisesm title, ...
	see: http://pechkin-mail.ru/?page=api_details&method=lists.update_merge
	*/
	public function lists_update_merge($list_id, $merge_id, $params = array()) {
		$required = array('list_id' => $list_id, 'merge_id' => $merge_id);
		$params = array_merge($required, $params);
		return $this->getData('lists.update_merge', $params);
	}

	//lists.delete_merge - Удалить дополнительное поле из адресной базы
	/*
	required: list_id, merge_id
	see: http://pechkin-mail.ru/?page=api_details&method=lists.delete_merge
	*/
	public function lists_delete_merge($list_id, $merge_id) {
		$params = array('list_id' => $list_id, 'merge_id' => $merge_id);
		return $this->getData('lists.delete_merge', $params);
	}


	/*******************************************************************
	************************* Работа с рассылками **********************
	********************************************************************/
	//campaigns.get - Получаем список рассылок пользователя
	/*
	optional: campaign_id, status, list_id, type
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.get
	*/
	public function campaigns_get($params = array()) {
		return $this->getData('campaigns.get', $params);
	}

	//campaigns.create - Создаем рассылку
	/*
	required: list_id
	optional: name. subject, ...
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.create
	*/
	public function campaigns_create($list_id, $params = array()) {
		$list_id = serialize($list_id);
		$required = array('list_id' => $list_id);
		$params = array_merge($required, $params);
		return $this->getData('campaigns.create', $params);
	}

	//campaigns.create_auto - Создаем авторассылку
	/*
	optional: list_id, name, subject
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.create_auto
	*/
	public function campaigns_create_auto($params = array()) {
		$params['list_id'] = serialize($params['list_id']);
		return $this->getData('campaigns.create_auto', $params);
	}

	//campaigns.update - Обновляем параметры рассылки
	/*
	required: campaign_id
	optional: list_id, name, subject, ...
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.update
	*/
	public function campaigns_update($campaign_id, $params = array()) {
		$required = array('campaign_id' => $campaign_id);
		$params['list_id'] = serialize($params['list_id']);
		$params = array_merge($required, $params);
		return $this->getData('campaigns.update', $params);
	}

	//campaigns.update_auto - Обновляем параметры авторассылки
	/*
	required: campaign_id
	optional: list_id, name, subject, ...
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.update_auto
	*/
	public function campaigns_update_auto($campaign_id, $params = array()) {
		$required = array('campaign_id' => $campaign_id);
		$params['list_id'] = serialize($params['list_id']);
		$params = array_merge($required, $params);
		return $this->getData('campaigns.update_auto', $params);
	}

	//campaigns.delete - Удаляем рассылку
	/*
	required: campaign_id
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.delete
	*/
	public function campaigns_delete($campaign_id) {
		$params = array('campaign_id' => $campaign_id);
		return $this->getData('campaigns.delete', $params);
	}

	//campaigns.attach - Прикрепляем файл
	/*
	required: campaign_id, url
	optional: name
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.attach
	*/
	public function campaigns_attach($campaign_id, $url, $params = array()) {
		$required = array('campaign_id' => $campaign_id, 'url' => $url);
		$params = array_merge($required, $params);
		return $this->getData('campaigns.attach', $params);
	}

	//campaigns.get_attachments - Получаем приложенные файлы
	/*
	required: campaign_id
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.get_attachments
	*/
	public function campaigns_get_attachments($campaign_id, $params = array()) {
		$required = array('campaign_id' => $campaign_id);
		$params = array_merge($required, $params);
		return $this->getData('campaigns.get_attachments', $params);
	}

	//campaigns.delete_attachments - Удаляем приложенный файл
	/*
	required: campaign_id, id
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.delete_attachments
	*/
	public function campaigns_delete_attachments($campaign_id, $id, $params = array()) {
		$required = array('campaign_id' => $campaign_id, 'id' => $id);
		$params = array_merge($required, $params);
		return $this->getData('campaigns.delete_attachments', $params);
	}

	//campaigns.get_templates - Получаем html шаблоны
	/*
	optional: name, id
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.get_templates
	*/
	public function campaigns_get_templates($params = array()) {
		return $this->getData('campaigns.get_templates', $params);
	}

	//campaigns.add_template - Добавляем html шаблон
	/*
	required: name, template
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.add_template
	*/
	public function campaigns_add_template($name, $template) {
		$params = array('name' => $name, 'template' => $template);
		return $this->getData('campaigns.add_templates', $params);
	}

	//campaigns.delete_template - Удаляем html шаблон
	/*
	required: id
	*/
	public function campaigns_delete_template($id) {
		$params = array('id' => $id);
		return $this->getData('campaigns.delete_templates', $params);
	}

	//campaigns.force_auto - Принудительно вызываем срабатывание авторассылки (при этом она должна быть активна)
	/*
	required: campaign_id, email
	optional: delay
	see: http://pechkin-mail.ru/?page=api_details&method=campaigns.force_auto
	*/
	public function campaigns_force_auto($campaign_id, $email, $params) {
		$email = $this->checkEmail($email);
		if ($email !== false)
			$required = array('campaign_id' => $campaign_id, 'email' => $email);
		else
			return $this->getError('6');
		$params = array_merge($required, $params);
		return $this->getData('campaigns.force_auto', $params);
	}

	/***************************************************************
	********************* Работа с отчетами ************************
	****************************************************************/
	//reports.send - Список отправленных писем в рассылке
	/*
	required: campaign_id
	optional: start, limit, order
	see: http://pechkin-mail.ru/?page=api_details&method=reports.send
	*/
	public function reports_sent($campaign_id, $params) {
		$required = array('campaign_id' => $campaign_id);
		$params = array_merge($required, $params);
		return $this->getData('reports.sent', $params);
	}

	//reports.delivered - Список доставленных писем в рассылке
	/*
	required: campaign_id
	optional: start, limit, order
	see: http://pechkin-mail.ru/?page=api_details&method=reports.delivered
	*/
	public function reports_delivered($campaign_id, $params) {
		$required = array('campaign_id' => $campaign_id);
		$params = array_merge($required, $params);
		return $this->getData('reports.delivered', $params);
	}

	//reports.opened - Список открытых писем в рассылке
	/*
	required: campaign_id
	optional: start, limit, order
	see: http://pechkin-mail.ru/?page=api_details&method=reports.opened
	*/
	public function reports_opened($campaign_id, $params) {
		$required = array('campaign_id' => $campaign_id);
		$params = array_merge($required, $params);
		return $this->getData('reports.opened', $params);
	}

	//reports.unsubscribed - Список писем отписавшихся подписчиков в рассылке
	/*
	required: campaign_id
	optional: start, limit, order
	see: http://pechkin-mail.ru/?page=api_details&method=reports.unsubscribed
	*/
	public function reports_unsubscribed($campaign_id, $params) {
		$required = array('campaign_id' => $campaign_id);
		$params = array_merge($required, $params);
		return $this->getData('reports.unsubscribed', $params);
	}

	//reports.bounced - Список возвратившихся писем в рассылке
	/*
	required: campaign_id
	optional: start, limit, order
	see: http://pechkin-mail.ru/?page=api_details&method=reports.bounced
	*/
	public function reports_bounced($campaign_id, $params) {
		$required = array('campaign_id' => $campaign_id);
		$params = array_merge($required, $params);
		return $this->getData('reports.unsubscribed', $params);
	}

	//reports.clickstat - Cтатистика по кликам по различным url в письме
	/*
	required: campaign_id
	see: http://pechkin-mail.ru/?page=api_details&method=reports.clickstat
	*/
	public function reports_clickstat($campaign_id) {
		$params = array('campaign_id' => $campaign_id);
		return $this->getData('reports.clickstat', $params);
	}

	//reports.bouncestat - Cтатистика по всевозможным причинам возврата письма
	/*
	required: campaign_id
	see: http://pechkin-mail.ru/?page=api_details&method=reports.bouncestat
	*/
	public function reports_bouncestat($campaign_id) {
		$params = array('campaign_id' => $campaign_id);
		return $this->getData('reports.bouncestat', $params);
	}

	//reports.summary - Краткая статистика по рассылке
	/*
	required: campaign_id
	see: http://pechkin-mail.ru/?page=api_details&method=reports.summary
	*/
	public function reports_summary($campaign_id) {
		$params = array('campaign_id' => $campaign_id);
		return $this->getData('reports.summary', $params);
	}

	//reports.clients - Cтатистика по браузерам, ОС и почтовым клиентам
	/*
	required: campaign_id
	see: http://pechkin-mail.ru/?page=api_details&method=reports.clients
	*/
	public function reports_clients($campaign_id) {
		$params = array('campaign_id' => $campaign_id);
		return $this->getData('reports.clients', $params);
	}

	//reports.geo - Cтатистика по регионам открытия
	/*
	required: campaign_id
	see: http://pechkin-mail.ru/?page=api_details&method=reports.geo
	*/
	public function reports_geo($campaign_id) {
		$params = array('campaign_id' => $campaign_id);
		return $this->getData('reports.geo', $params);
	}

}