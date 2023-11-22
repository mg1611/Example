
<?php

namespace NW\WebService\References\Operations\Notification;

use NW\Model\Seller; // Импортируем Seller class
use NW\Model\Contractor; // Импортируем Contractor class
use NW\Model\Employee; // Импортируем Employee class
use NW\Model\Status; // Импортируем Status class (предполагая, что он существует где-то в вашем приложении)

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): void // Определяем возвращаемый тип данных
    {
        $data = (array)$this->getRequest('data');
        $resellerId = (int)$data['resellerId']; // Парсим данные в тип int
        $notificationType = (int)$data['notificationType'];

        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];

        if (empty($resellerId)) { // Убираем лишний приведение типа
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result; // Возвращаем $result вне зависимости от типа
        }

        if (empty($notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($resellerId); // Вызываем функцию класса Seller
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }

        $client = Contractor::getById((int)$data['clientId']); // Парсим данные в тип int
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) { // Добавляем пропущенные операторы сравнения
            throw new \Exception('Client not found!', 400);
        }

        $cFullName = $client->getFullName() ?: $client->name; // Используем оператор объединения с null

        $cr = Employee::getById((int)$data['creatorId']);
        if ($cr === null) {
            throw new \Exception('Creator not found!', 400);
        }

        $et = Employee::getById((int)$data['expertId']);
        if ($et === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                    'FROM' => Status::getName((int)$data['differences']['from']),
                    'TO'   => Status::getName((int)$data['differences']['to']),
                ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID'       => (int)$data['complaintId'],
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => (int)$data['creatorId'],
            'CREATOR_NAME'       => $cr->getFullName(),
            'EXPERT_ID'          => (int)$data['expertId'],
            'EXPERT_NAME'        => $et->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId); // Предполагая, что это функция из объявленной области видимости
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn'); // Предполагая, что это функция из объявленной области видимости
        
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
              
