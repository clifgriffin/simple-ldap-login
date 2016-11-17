# Configurando SSO no Ambiente Debian/Apache #

Siga este documento para configurar o SSO (Single Sign-on) entre a autenticação do Windows e o servidor Apache, usando o módulo **auth_ntlm_winbind**.

## Configurando o nome da máquina ##
Edite o arquivo `/etc/hosts` e configure o nome completo da máquina, seguindo o modelo abaixo:

````
127.0.0.1       localhost
127.0.1.1       WEB1.seu.dominio       WEB1
```

No arquivo `/etc/hostname`, informe o nome da máquina conforme configurado no arquivo `/etc/hosts`:

```
WEB1
```

## Sincronizando relógio com o Active Directory ##
Para evitar problemas ao incluir a máquina no domínio, você deve sincronizar o relógio da máquina Debian com o relógio do AD.
Instale o o ntp:

```
aptitude intall ntpdate ntp
```

Edite o arquivo `/etc/ntp.conf`, deixando apenas os servidores LDAP's. Exemplo:

```
# IP do servidor LDAP
server 192.168.1.1
```

Reinicie o serviço:

```
service ntp restart
```

Sincronize o relógio:

```
ntpdate -s 192.168.1.1
```

## Configurando Kerberos ##

Instale os seguintes pacotes:

```
aptitude install krb5-user krb5-config libpam-krb5
```

Edite o arquivo `/etc/krb5.conf` de acordo com o exemplo abaixo:

```
[logging]
        default = FILE:/var/log/krb5.log
        kdc = FILE:/var/log/krb5kdc.log
        admin_server = FILE:/var/log/kadmind.log

[libdefaults]
        ticket_lifetime = 24000
        dns_lookup_realm = false
        dns_lookup_kdc = false
        clockskew = 300
        kdc_timesync = 1
        default_realm = SEU.DOMINIO

[realms]
SEU.DOMINIO = {
        kdc = 192.168.1.1
        }

[domain_realm]
 .seu.dominio = SEU.DOMINIO
  seu.dominio = SEU.DOMINIO
```

Para testar a configuração, use os comandos abaixo:

```
kinit <usuarioAD> # Se estiver Ok, pedirá sua senha e finalizará sem mensagem.
```

```
klist # Irá exibir o token gerado pelo kinit anterior.
```

```
kdestroy # Destrói o token gerado.
```

## Configurando Samba ##

Instale os seguintes pacotes:

```
aptitude install samba winbind libnss-winbind libpam-winbind
```

Edite o arquivo `/etc/samba/smb.conf` seguindo o exemplo a seguir (consulte a documentação do Samba para entender cada parâmetro):

```
[global]
	security = ADS
	realm= SEU.DOMINIO 
	workgroup = DOMINIO 
	netbios name = WEB1
	server string = Descrição da máquina WEB1

	idmap config * : range = 2000-9999
	idmap config * : backend = tdb

	idmap config DOMINIO : schema_mode = rfc2307
	idmap config DOMINIO : range = 100000-399999
	idmap config DOMINIO : default = yes
	idmap config DOMINIO : backend = rid

	winbind enum users = yes
	winbind enum groups = yes
	
	template homedir = /home/%D/%U
	template shell = /bin/bash 
	
	client use spnego = yes
	winbind use default domain = yes
	restrict anonymous = 2
	winbind refresh tickets = yes 
```

**Dica**: Para testar a configuração do Samba, execute o comando `testparm`.

Reinicie os serviços:

```
service winbind restart
service smbd restart
```

## Ingressando a máquina no domínio ##

Execute o comando abaixo: 

```
net ads join -U usuarioAdministradorDoDominio
```

## Configurando Apache ##

Instale o módulo **auth_ntlm_winbind** e o habilite:

```
aptitude install libapache2-mod-auth-ntlm-winbind
a2enmod auth_ntlm_winbind
```

Edite o arquivo `/etc/apache2/apache2.conf` e adicione o módulo de autenticação à pasta do Wordpress. 
Se o Wordpress estiver instalado na pasta `/var/www`, a configuração necessária seria:

```ApacheConf
<Directory /var/www/>
    Options FollowSymLinks
    AllowOverride FileInfo
    AuthName "Acesso Intranet"
    NTLMAuth on
    NTLMAuthHelper "/usr/bin/ntlm_auth --domain=seu.dominio --helper-protocol=squid-2.5-ntlmssp"
    NTLMBasicAuthoritative on
    AuthType NTLM
    require valid-user
</Directory>
```

Execute os comandos a seguir, para dar permissão ao usuário do Apache e corrigir um bug:

```
usermod -a -G winbindd_priv www-data
chgrp winbindd_priv /var/lib/samba/winbindd_privileged
ln -s /var/lib/samba/winbindd_privileged/pipe /var/run/samba/winbindd_privileged/pipe
```

Feito isso, basta acessar o Wordpress e habilitar o **SSO** na tela de configuração do `simple-LDAP-plugin`.

## Configurando o Firefox ##

No caso do Firefox, você deve adicionar o seu domínio como confiável. Para isso, através do 
`about:config` edite a chave `network.automatic-ntlm-auth.trusted-uris`, colocando o seu domínio na forma
`.seu.dominio`.


