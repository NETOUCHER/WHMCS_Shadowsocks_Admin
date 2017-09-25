# WHMCS-Shadowsocks [Server Module]

This module is for shadowsocks-manyuser.</br>

Version Update:</br>
2.0.2 Integrated with <strong>QR code generator</strong>. *You must configure the module manually.*</br>
2.0.1 A Shadowsocks link generato
2.0.0 Converted All Mysql queries into PDO_Mysql queries.</br>
1.0.0 <a href="https://github.com/soft-wiki/whmcs-shadowsocks">whmcs-shadowsocks</a> by Tension (Verification Request)</br>

#
SQL File can be found at https://github.com/NeToucher/shadowsocks-rm/tree/manyuser/shadowsocks

# Configure your module
<strong>****MOST IMPORTANT****</strong></br>
You must edit the URL in <strong>shadowsocks.php</strong> on line <strong>540</strong> and line <strong>545</strong> </br>

    $imgs .= '<img src="https://example.com/modules/servers/shadowsocks/QR/qrcode.php?text='.$output.'" />&nbsp;';

to ensure your QR code could be loaded. </br>
Then you could edit the domain name from <strong>example.com</strong> to yours in <strong>QR/qrcode.php</strong></br>

    if(strpos($_SERVER['HTTP_REFERER'], 'example.com') == FALSE)
       {
          QRcode::png("Fuck your self idiot.");
          exit;
        }

and clear the remark to enable the anti-abuse for your generator. The code in remark area could define the domain which could use the generator legally and all the visiting from the other domain will be block with another QR code which could be decoded to a curse.
