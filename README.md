CoinRemitter plugin for Prestashop
===

Coinremitter is [crypto payments](http://coinremitter.com) service for Prestashop. Accept Bitcoin, Tron, Binance (BEP20), BitcoinCash, Ethereum, Litecoin, Dogecoin, Tether, Dash, Monero etc.



**What Is Crypto Payment Processor?**

Crypto Payment Processor acts as a bridge between the merchant’s website and the cryptocurrency network, allowing the merchant to receive payments in the form of cryptocurrency.


Requirements
---
* For the integration process with coinremitter, users must need to have Prestashop version 1.7+
* If you don't have a Coinremitter account then please consider making it one.  [Create Account ](https://coinremitter.com/signup)

Plugin Installation Guide:
---
>PATH: SIdebar > Modules > Module manager > Upload a module > Select File 

1. Download the zip file from this github repo extract it.
2. Then open the extracted folder and find a folder named prestashop-master. Then rename it to **coinremitter** and compress it.
2. Log into your admin panel.
3. Click on Modules at the sidebar section.

    ![Coinremitter-Plugin-Installation](https://coinremitter.com/assets/img/screenshots/prestashop/plugin_installation.png)

4. There are two options “Module manager” and “Module Catalog” under Modules.
5. Click on “Module Manager”
6. Then click on "Upload a Module" button. it will open popup where you have to upload the zip file

    ![Coinremitter-Plugin-Installation](https://coinremitter.com/assets/img/screenshots/prestashop/plugin_installation_2.png)

    ![Coinremitter-Plugin-Installation](https://coinremitter.com/assets/img/screenshots/prestashop/plugin_installation_3.png)

7. Now select the coinremitter.zip file and upload it.
8. There you go! You’ve successfully installed the plugin.

To activate it completely follow the instructions below:

Plugin Configuration:
---
>PATH: Sidebar > Modules > Module manager > Other section > Coinremitter plugin > Configure > SAVE

1. Head to the sidebar on the Prestashop admin panel.
2. Click on Modules and then select Module Manager.
3. In the module manager page, you have to find the “Other” section.
    
    ![Coinremitter-Plugin-Installation](https://coinremitter.com/assets/img/screenshots/prestashop/configuration_plug_1.png)

4. You’ll find the “Coinremitter” plugin there.
5. Now, click on “Configure” at the end of the same line.
6. The configure settings page will open.
 
    ![Coinremitter-Plugin-Installation](https://coinremitter.com/assets/img/screenshots/prestashop/configuration_plug_2.png)

There you will need to fill up some details according to your preferences:

**TITLE**:
The title you write, which will appear to the customers on the checkout page.

**DESCRIPTION:**
You can add a few details to tell the customer something important before the customer takes any step during checkout.

**INVOICE EXPIRY TIME IN MINUTES:**
If you set the value 30 then the invoice created will expire after 30 minutes.

**ORDER STATUS:**
Set the order status, when customers successfully make payment using cryptocurrency.
You’re all SET, save the setting.

Create a Wallet:
---
>PATH: More > Coinremitter > Add wallet

1. Go to the sidebar on the Prestashop’s admin panel.
2. Look all the way down and click on the “Coinremitter” which is right under the “MORE” option.

    ![Coinremitter-Plugin-Add_wallet](https://coinremitter.com/assets/img/screenshots/prestashop/create_wallet.png)

3. The wallet list page will open.
4. To add a new wallet, click on the plus button located on the top right corner of the page.
5. A new page will appear, where you'll see multiple options such as **Select Coin, API key, Password and Exchange Rate Multiplier and Minimum Invoice Value.**

    ![Coinremitter-Plugin-Add_wallet](https://coinremitter.com/assets/img/screenshots/prestashop/create_wallet_2.png)

6. **SELECT COIN :**  Select the coin that you want to support in your store.
7. **API KEY :** Go to the website of Coinremitter and login to your account and get your API key from there. If you have any trouble getting your password and API key then [click here](https://blog.coinremitter.com/how-to-get-api-key-and-password-of-coinremitter-wallet/) to get the understanding.
8. **PASSWORD :** Type the password that you have set while creating a new wallet in your merchant panel.
9. **EXCHANGE RATE MULTIPLIER:**
The initial setting is 1. It is a multiplier of prices. For example, if you set it to 1.10, then cryptocurrency prices would grow by 10%, and for a 5% discount, you can set it to 0.95 in this text box.
10. **Minimum Invoice Value:**
Setting the minimum invoice limit is necessary, The generated invoice won’t be less than the minimum invoice limit.
Click on Save and you’re good to go!

How to Make Payment:
---
>NOTE: You can test your first order using the Test Coin.

* Now add some products to your cart and proceed to the checkout page. Choose the "Payment with Cryptocurrency" payment form. 
    If you changed the title text on the configuration page of the coinremitter then it will be shown as an option here. Click on the button to continue.
   
    ![Coinremitter-Plugin-Make_Payment](https://coinremitter.com/assets/img/screenshots/prestashop/how_make_payment.png)

* Customers will select the coin that they like to pay for. Make sure you've added a wallet to your Prestashop admin panel.
* Now customers will click on “Order with an obligation to pay”, The system will then automatically create an invoice that will appear on the screen.
    
    ![Coinremitter-Plugin-inovice-page](https://coinremitter.com/assets/img/screenshots/prestashop/how_make_payment_2.png)

* It will instantly redirect to the success page once the payment has been confirmed on the blockchain.

    ![Coinremitter-Plugin-thank-you-page](https://coinremitter.com/assets/img/screenshots/prestashop/how_make_payment_3.png) 

* That’s all, Your order has been successfully placed.

    
Check Your Order Details:
---
* To check your order, go to the sidebar on the **Admin panel** of Prestashop.
* From the order menu select “orders”, after a click on it, you will see your multiple orders list. Select one of these orders that have been paid using the coinremitter payment option.
* Click on the order and you will be redirected to the order view page.
* There you’ll see the complete order detail, conversion rate, and payment history.

    ![Coinremitter-Plugin-payment-detail](https://coinremitter.com/assets/img/screenshots/prestashop/check_order.png) 
Now you are ready to accept cryptocurrency payments into your Prestashop website.
>NOTE: Don't forget to remove Test Coin wallet from your admin panel and add other coin wallets other than test coin
