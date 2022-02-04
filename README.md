Yii 2 raamistiku laiendus VauID versiooni 2.0 kasutamiseks
==========================================================

https://github.com/erikuus/yii2-vauid2-extension


Eelistatud paigaldus
--------------------

Eelistatud paigaldusviis on **composeri** kaudu.

Lisa rakenduse **composer.json** faili paketi nimi ja versioon:

```json
"require": {
    "rahvusarhiiv/vauid": "1.0"
}
```

ja githubi repositoorium:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/erikuus/yii2-vauid2-extension"
    }
]
```

Seejärel jooksuta terminalis käsku

```shell
composer update
```

Alternatiivne paigaldus
-----------------------

Laadi kõik laienduse failid Githubist alla ja paigalda rakenduse **vendor/rahvusarhiiv/vauid/** kausta.

Lisa faili **vendor/yiisoft/extensions.php** järgmised read:

```php
'rahvusarhiiv/vauid' => [
    'name' => 'rahvusarhiiv/vauid',
    'version' => '1.0.0.0',
    'alias' => [
        '@rahvusarhiiv/vauid' => $vendorDir . '/rahvusarhiiv/vauid',
    ],
],
```

Minimaalne seadistus
--------------------
*Selle seadistuse puhul ei vaja rakendus eraldi kasutaja mudelit ja tabelit*

Määra konfiguratsioonifailis **user** komponendi **identityClass** väärtuseks **rahvusarhiiv\vauid\VauUserIdentity**:

```php
'user' => [
    'identityClass' => 'rahvusarhiiv\vauid\VauUserIdentity'
]
```

Lisa konfiguratsioonifailis komponentide hulka **rahvusarhiiv\vauid\VauSecurityManager**, kus **###** asemel on salajane võti:

```php
'vauSecurityManager' => [
    'class' => 'rahvusarhiiv\vauid\VauSecurityManager',
    'validationKey' => '###'
]
```

Seadista **SiteController::actions()** järgmiselt:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction'
        ]
    ];
}
```

Suuna **SiteController::actionLogin** VauID sisselogimise teenuse aadressile, määrates **remoteUrl** väärtuseks eelnevalt defineeritud aktsiooni **SiteController::vauLogin**:

```php
public function actionLogin()
{
    $vauUrl = "https://www.ra.ee/vau/index.php/site/login?v=2&s=user_role&remoteUrl=";    
    $remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/vauLogin", "https");
    $this->redirect($vauUrl . $remoteUrl);
}
```

Suuna väljalogimise link VauID väljalogimise teenuse aadressile, määrates **remoteUrl** väärtuseks **SiteController::actionLogout**:

```php
$remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/logout", "https");
echo Html::a("Logout", "https://www.ra.ee/vau/index.php/site/logout?remoteUrl=" . $remoteUrl);
```

Sellise seadistuse puhul loob laiendus pärast edukat VAU kaudu sisselogimist rakenduses sessiooni, kus:

- **Yii::$app->user->id** on kasutaja id VAU-s
- **Yii::$app->user->identity->vauData** on massiiv, mis sisaldab kõiki VAU saadetud andmeid kasutaja kohta

Juurdepääsu piiramine
---------------------
*Spetsiaalse parameetri kaudu saab piirata, kes ja kuidas võivad VAU kaudu rakendusse siseneda*

Kui **authOptions['accessRules']['safelogin'] === true**, siis autoriseeritakse ainult kasutajad, kes autentisid ennast VAU-s ID-kaardi, Mobiil-ID või Smart-ID kaudu:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'safelogin' => true
                ]
            ]
        ]
    ];
}
```

Kui **authOptions['accessRules']['safehost'] === true**, siis autoriseeritakse ainult kasutajad, kes autentisid ennast arhiivi sisevõrgust:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'safehost' => true
                ]
            ]
        ]
    ];
}
```

Kui **authOptions['accessRules']['safe'] === true**, siis autoriseeritakse ainult kasutajad, kes autentisid ennast ID-kaardi, Mobiil-ID, Smart-ID kaudu
või arhiivi sisevõrgust:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'safe' => true
                ]
            ]
        ]
    ];
}
```

Kui **authOptions['accessRules']['employee'] === true**, siis autoriseeritakse ainult kasutajad, kellele on VAU-s antud töötaja õigused:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'employee' => true
                ]
            ]
        ]
    ];
}
```

Kui on defineeritud **authOptions['accessRules']['roles']**, siis autoriseeritakse ainult kasutajad, kellele on VAU-s määratud mõni neist rollidest:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'roles' => [
                        'ClientManager',
                        'EnquiryManager'
                    ]
                ]
            ]
        ]
    ];
}
```

Tavaline seadistus
------------------
*Rakenduses on kasutaja mudel ja tabel, mille andmeid sünkroonitakse VAU andmetega*

Et näide oleks võimalikult selge, oletame, et rakendus hoiab kasutajate andmeid tabelis, mille tulpade nimed on eestikeelsed:

```sql
CREATE TABLE kasutaja
(
    kood serial NOT NULL,
    eesnimi character varying(64),
    perekonnanimi character varying(64),
    epost character varying(128),
    telefon character varying(64),
    CONSTRAINT pk_kasutaja PRIMARY KEY (kood)
)
```

Rakenduses on sellest tabelist genereeritud **class Kasutaja extends \yii\db\ActiveRecord implements IdentityInterface**.

See klass on konfiguratsioonifailis **user** komponendi **identityClass**:

```php
'user' => [
    'identityClass' => 'app\models\Kasutaja',
]
```

Sarnaselt minimaalse seadistusega lisa konfiguratsioonifailis komponentide hulka **rahvusarhiiv\vauid\VauSecurityManager**, kus **###** asemel on salajane võti:

```php
'vauSecurityManager' => [
    'class' => 'rahvusarhiiv\vauid\VauSecurityManager',
    'validationKey' => '###'
]
```

Suuna **SiteController::actionLogin** VauID sisselogimise teenuse aadressile, määrates **remoteUrl** väärtuseks aktsiooni **SiteController::vauLogin**:

```php
public function actionLogin()
{
    $vauUrl = "https://www.ra.ee/vau/index.php/site/login?v=2&s=user_role&remoteUrl=";
    $remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/vauLogin", "https");
    $this->redirect($vauUrl . $remoteUrl);
}
```

Suuna väljalogimise link VauID väljalogimise teenuse aadressile, määrates **remoteUrl** väärtuseks **SiteController::actionLogout**:

```php
$remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/logout", "https");
echo Html::a("Logout", "https://www.ra.ee/vau/index.php/site/logout?remoteUrl=" . $remoteUrl);
```

Nüüd, kui me soovime teha nii, et rakenduse kasutajad on vastavuses VAU kasutajatega ja rakendusse sisselogimine käib VAU kaudu, siis peame kõigepealt lisama tabelisse uue tulba VAU kasutaja ID jaoks:

```sql
CREATE TABLE kasutaja
(
    kood serial NOT NULL,
    eesnimi character varying(64),
    perekonnanimi character varying(64),
    epost character varying(128),
    telefon character varying(64),
    vau_kood integer, -- uus tulp VAU kasutaja ID jaoks
    CONSTRAINT pk_kasutaja PRIMARY KEY (kood)
)
```

Alustame kõige lihtsamast kasutusjuhust. Loome seose rakenduse kasutaja ja VAU kasutaja vahele käsitsi, lisades väljale **vau_kood** kasutaja ID VAU andmebaasis. Kui see on tehtud, määrame **rahvusarhiiv\vauid\VauLoginAction** parameetri **dataMapping** järgmiselt:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'dataMapping' => [
                    'model' => 'app\models\Kasutaja',
                    'id' => 'vau_kood'
                ]
            ]
        ]
    ];
}
```

Sellise seadistuse puhul õnnestub VAU kaudu rakendusse sisse logida ainult neil VAU kasutajatel, kelle ID leidub tabeli **kasutaja** väljal **vau_kood**.
Rakenduses käivitatakse sessioon, kus:

- **Yii::$app->user->id** on kasutaja kood rakenduses (mitte kasutaja id VAU-s)
- **Yii::$app->user->identity->vauData** ei ole olemas (kasutada saab **$app->user->identity->eesnimi** jne)

Kui me soovime, et kasutaja andmed rakenduses oleksid sünkroonitud kasutaja andmetega VAU-s, lülitame sisse **authOptions['dataMapping']['update']** ja kaardistame seosed VAU ja rakenduse andmete vahel **authOptions['dataMapping']['attributes']** abil. Sellise seadistuse korral kirjutatakse rakenduse andmed üle VAU andmetega iga kord, kui kasutaja VAU kaudu rakendusse siseneb:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'dataMapping' => [
                    'model' => 'app\models\Kasutaja',
                    'id' => 'vau_kood',
                    'update' => true,
                    'attributes' => [
                        'firstname' => 'eesnimi',
                        'lastname' => 'perekonnanimi',
                        'email' => 'epost',
                        'phone' => 'telefon'
                    ]
                ]
            ]
        ]
    ];
}
```

Pane tähele, et kui sa määrad seose ka **roles** jaoks, on väärtuse tüüp **array**. Mõistagi ei saa seda otse andmebaasi salvestada. Küll aga saab selle väärtusega manipuleerida **Kasutaja** klassis vastavalt vajadusele.

Kõik ülaltoodud seadistused lubavad rakendusse siseneda ainult neil VAU kasutajatel, kelle VAU ID on juba rakenduse andmebaasis kirjas. Lülitades sisse **authOptions['dataMapping']['create']** lubame siseneda ka uutel kasutajatel: kui tabelist **kasutaja** ei leita rida, kus **vau_kood** võrdub VAU kasutaja ID-ga, luuakse tabelisse VAU andmete alusel uus rida, uus kasutaja:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'dataMapping' => [
                    'model' => 'app\models\Kasutaja',
                    'id' => 'vau_kood',
                    'update' => true,
                    'create' => true,
                    'attributes' => [
                        'firstname' => 'eesnimi',
                        'lastname' => 'perekonnanimi',
                        'email' => 'epost',
                        'phone' => 'telefon'
                    ]
                ]
            ]
        ]
    ];
}
```

Lõpuks on võimalik määrata ka **authOptions['dataMapping']['scenario']** abil stsenaarium VAU andmete salvestamiseks rakenduses:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'rahvusarhiiv\vauid\VauLoginAction',
            'authOptions' => [
                'dataMapping' => [
                    'model' => 'app\models\Kasutaja',
                    'scenario' => 'vau',
                    'id' => 'vau_kood',
                    'update' => true,
                    'create' => true,
                    'attributes' => [
                        'firstname' => 'eesnimi',
                        'lastname' => 'perekonnanimi',
                        'email' => 'epost',
                        'phone' => 'telefon'
                    ]
                ]
            ]
        ]
    ];
}
```
