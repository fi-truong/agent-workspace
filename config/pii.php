<?php
 
return [
 
    /*

    |--------------------------------------------------------------------------

    | Email domain whitelist

    |--------------------------------------------------------------------------

    |

    | Domain email KHÔNG bị che vì đặt theo chức vụ/phòng ban, không định danh

    | cá nhân (vd hr@lsts.edu.vn). CHỈ thêm domain vào đây nếu CHẮC CHẮN 100%

    | mailbox dưới domain đó không có dạng ten.ho@domain của cá nhân cụ thể —

    | kể cả giáo viên, học sinh, phụ huynh nếu họ cũng dùng chung domain này.

    | Nhiều domain thì phân cách bằng dấu phẩy trong .env.

    |

    */

    'email_domain_whitelist' => env('PII_EMAIL_DOMAIN_WHITELIST', 'lsts.edu.vn'),

];

