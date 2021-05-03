Yii 2 raamistiku laiendus VauID versiooni 2.0 kasutamiseks
==========================================================

Minimaalne seadistus
--------------------
*Selle seadistuse puhul ei vaja rakendus eraldi kasutaja mudelit ja tabelit*

Määra konfiguratsioonifailis `user` komponendi identityClass väärtuseks `ra\vauid\VauUserIdentity`:

```php
'user' => [
    'identityClass' => 'ra\vauid\VauUserIdentity'
]
```

Lisa konfiguratsioonifailis komponentide hulka `ra\vauid\VauSecurityManager`, kus `###` asemel on salajane võti:

```php
'vauSecurityManager' => [
    'class' => 'ra\vauid\VauSecurityManager',
    'validationKey' => '###'
]
```

Seadista `SiteController::actions()` järgmiselt:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction'
        ]
    ];
}
```

Suuna `SiteController::actionLogin` VauID sisselogimise teenuse aadressile, määrates remoteUrl väärtuseks eelnevalt defineeritud aktsiooni `SiteController::vauLogin`:

```php
public function actionLogin()
{
    $remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/vauLogin", "https");
    $this->redirect("http://www.ra.ee/vau/index.php/site/login?v=2&s=user&remoteUrl" . $remoteUrl);
}
```

Suuna väljalogimise link VauID väljalogimise teenuse aadressile, määrates `remoteUrl` väärtuseks `SiteController::actionLogout`:

```php
$remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/logout", "https");
echo Html::a("Logout", "http://www.ra.ee/vau/index.php/site/logout?remoteUrl=" . $remoteUrl;
```

Sellise seadistuse puhul loob laiendus pärast edukat VAU kaudu sisselogimist rakenduses sessiooni, kus:

- `Yii::$app->user->id` kasutaja id VAU-s
- `Yii::$app->user->identity->vauData` massiiv, mis sisaldab kõiki VAU saadetud andmeid kasutaja kohta

Juurdepääsu piiramine
---------------------
*`ra\vauid\VauLoginAction` parameetri `authOptions` kaudu saab piirata, kes ja kuidas võivad VAU kaudu rakendusse siseneda*

Kui `authOptions['accessRules']['safelogin'] === true`, siis autoriseeritakse ainult kasutajad, kes autentisid ennast VAU-s ID-kaardi, Mobiil-ID või Smart-ID kaudu:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'safelogin' => true
                ]
            ]
        ]
    ];
}
```

Kui `authOptions['accessRules']['safehost'] === true`, siis autoriseeritakse ainult kasutajad, kes autentisid ennast arhiivi sisevõrgust:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'safehost' => true
                ]
            ]
        ]
    ];
}
```

Kui `authOptions['accessRules']['safe'] === true`, siis autoriseeritakse ainult kasutajad, kes autentisid ennast ID-kaardi, Mobiil-ID, Smart-ID kaudu
või arhiivi sisevõrgust:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'safe' => true
                ]
            ]
        ]
    ];
}
```

Kui `authOptions['accessRules']['employee'] === true`, siis autoriseeritakse ainult kasutajad, kellele on VAU-s antud töötaja õigused:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
            'authOptions' => [
                'accessRules' => [
                    'employee' => true
                ]
            ]
        ]
    ];
}
```

Kui on defineeritud `authOptions['accessRules']['roles']`, siis autoriseeritakse ainult kasutajad, kellele on VAU-s määratud mõni neist rollidest:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
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

Rakenduses on sellest tabelist genereeritud `class Kasutaja extends \yii\db\ActiveRecord implements IdentityInterface`.

See klass on konfiguratsioonifailis `user` komponendi `identityClass`:

```php
'user' => [
    'identityClass' => 'app\models\Kasutaja',
]
```

Sarnaselt minimaalse seadistusega lisa konfiguratsioonifailis komponentide hulka `ra\vauid\VauSecurityManager`, kus `###` asemel on salajane võti:

```php
'vauSecurityManager' => [
    'class' => 'ra\vauid\VauSecurityManager',
    'validationKey' => '###'
]
```

Suuna `SiteController::actionLogin` VauID sisselogimise teenuse aadressile, määrates remoteUrl väärtuseks aktsiooni `SiteController::vauLogin`:

```php
public function actionLogin()
{
    $remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/vauLogin", "https");
    $this->redirect("http://www.ra.ee/vau/index.php/site/login?v=2&s=user&remoteUrl" . $remoteUrl);
}
```

Suuna väljalogimise link VauID väljalogimise teenuse aadressile, määrates remoteUrl väärtuseks `SiteController::actionLogout`:

```php
$remoteUrl = Yii::$app->urlManager->createAbsoluteUrl("/site/logout", "https");
echo Html::a("Logout", "http://www.ra.ee/vau/index.php/site/logout?remoteUrl=" . $remoteUrl;
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

Alustame kõige lihtsamast kasutusjuhust. Loome seose rakenduse kasutaja ja VAU kasutaja vahele käsitsi, lisades väljale vau_kood kasutaja ID VAU andmebaasis. Kui see on tehtud, määrame `ra\vauid\VauLoginAction` parameetri `dataMapping` järgmiselt:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
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

Sellise seadistuse puhul õnnestub VAU kaudu rakendusse sisse logida ainult neil VAU kasutajatel, kelle ID leidub tabeli `kasutaja` väljal `vau_kood`.
Rakenduses käivitatakse sessioon, kus:

- `Yii::$app->user->id` kasutaja kood rakenduses (mitte kasutaja id VAU-s)
- `Yii::$app->user->identity->vauData` **ei ole olemas** (kasutada saab `$app->user->identity->eesnimi` jne)

Kui me soovime, et kasutaja andmed rakenduses oleksid sünkronitud kasutaja andmetega VAU-s, lülitame sisse `authOptions['dataMapping']['update']` ja kaardistame seosed VAU ja rakenduse andmete vahel `authOptions['dataMapping']['attributes']` abil. Sellise seadistuse korral kirjutatakse rakenduse andmed üle VAU andmetege iga kord, kui kasutaja VAU kaudu rakendusse siseneb:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
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

Pane tähele, et kui sa määrad seose ka roles jaoks, on väärtuse tüüp `array`. Mõistagi ei saa seda otse andmebaasi salvestada. Küll aga saab selle väärtusega manipuleerida Kasutaja klassis vastavalt vajadusele.

Kõik ülaltoodud seadistused lubavad rakendusse siseneda ainult neil VAU kasutajatel, kelle VAU ID on juba rakenduse andmebaasis kirjas. Lülitades sisse `authOptions['dataMapping']['create']` lubame siseneda ka uutel kasutajatel: kui tabelist kasutaja ei leita rida, kus `vau_kood` võrdub VAU kasutaja ID-ga, luuakse tabelisse VAU andmete alusel uus rida, uus kasutaja:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
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

Lõpuks on võimalik määrata ka `authOptions['dataMapping']['scenario']` abil stsenaarium VAU andmete salvestamiseks rakenduses. See võib-olla vajalik näiteks valideerimise reeglite määramisel, kui soovitakse VAU andmete jaoks teha mingeid erandeid:

```php
public function actions()
{
    return [
        'vauLogin' => [
            'class' => 'ra\vauid\VauLoginAction',
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
