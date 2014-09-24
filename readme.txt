=== PayMaster for WooCommerce ===
Contributors: Qazomardok
Tags: paymaster, payment getaway, woo commerce, woocommerce, ecommerce
Requires at least: 3.0
Tested up to: 4.0.1
Stable tag: trunk

Allows you to use PayMaster payment gateway with the WooCommerce plugin.

== Description ==

Для работы с плагином требуется активный аккаунт в системе PayMaster. PayMaster работают только с ИП или юридическими лицами. Минимальный статус аккаунта: "Тестовый режим, ожидает проверки"

После активации плагина зайти на страницу WooCommerce->Настройки->Оплата->Paymaster. Заполните все поля настроек, следуя подсказкам.


В paymaster:

1. Добавьте Ваш сайт;
2. На странице Учетная запись -> Список сайтов -> Настройки":
<ul style="list-style:none;">
<li>Выберите и запомните "Тип подписи"</li>
<li>Придумайте и запомните "Секретный ключ"</li>
</ul>

В разделе "Обратные вызовы" укажите следующие параметры:
<ul style="list-style:none;">
<li>Payment notification: http://your_domain/?wc-api=wc_paymaster&paymaster=notification</li>
<li>Success redirect: http://your_domain/?wc-api=wc_paymaster&paymaster=success</li>
<li>Failure redirect: http://your_domain/?wc-api=wc_paymaster&paymaster=failure</li>
<li>Invoice confirmation: http://your_domain/?wc-api=wc_paymaster&paymaster=invoice</li>
<li>Метод отсылки данных для всех строк: POST запрос</li>
</ul>
Где "your_domain" - домен Вашего сайта.

3. На странице "Учетная запись -> Пользователи":
<ul style="list-style:none;">
<li>Создайте нового пользователя с правами "Бухгалтер" и способом входа "Автоматический"</li>
</ul>


Более подробно на [странице плагина](http://qazomardok.ru/woocommerce-paymaster/)


== Installation ==
1. Убедитесь что у вас установлена последняя версия плагина [WooCommerce](/www.woothemes.com/woocommerce)
2. Распакуйте архив и загрузите "paymaster-for-woocommerce" в папку ваш-домен/wp-content/plugins
3. Активируйте плагин


== Changelog ==
= 0.1.9 =
* Адаптация для Woocommerce 2.0 и Wordpress 4.0. На ранних версиях не будет работать плнировщик.
= 0.1.5 =
* Закрытый релиз.
= 0.1.4 =
* Реализация возвратов.
= 0.1.3 =
* Планировщик
= 0.1.4 =
* Графическое оформление.
= 0.1.3 =
* Получение статуса платежей на странице заказов через Ajax. 
= 0.1.1 =
* Исправления ошибок
= 0.1 =
* Релиз Develop-версии плагина
