# Configurando SSO no Ambiente CentOS/Apache #

Siga este documento para configurar o SSO (Single Sign-on) entre a autenticação do Windows e o servidor Apache, usando o módulo **auth_ntlm_winbind**.

## Configurando o nome da máquina ##

No arquivo `/etc/hostname`, informe o nome completo da máquina(FQDN):

```
WEB1.seu.dominio
```

## Sincronizando relógio com o Active Directory ##
Para evitar problemas ao incluir a máquina no domínio, você deve sincronizar o relógio da máquina CentOS com o relógio do AD.
Instale o o ntp:

```
yum intall ntpdate ntp
```

Edite o arquivo `/etc/ntp.conf`, deixando apenas os servidores LDAP's. Exemplo:

```
# IP do servidor LDAP
server 192.168.1.1
```

Reinicie o serviço:

```
systemctl restart ntpd
```

Sincronize o relógio:

```
ntpdate -s 192.168.1.1
```

## Configurando Kerberos ##

Instale os seguintes pacotes:

```
yum install krb5-libs krb5-workstation pam_krb5
```

Edite o arquivo `/etc/krb5.conf` de acordo com o exemplo abaixo:

```
[logging]
        default = FILE:/var/log/krb5.log
        kdc = FILE:/var/log/krb5kdc.log
        admin_server = FILE:/var/log/kadmind.log

[libdefaults]
        dns_lookup_realm = false
        ticket_lifetime = 24h
        renew_lifetime = 7d
        forwardable = true
        rdns = false
        default_realm = SEU.DOMINIO
        default_ccache_name = KEYRING:persistent:%{uid}

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
yum install samba samba-winbind samba-winbind-clients oddjob-mkhomedir samba-winbind-krb5-locator
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

Reinicie o serviço:

```
systemctl restart smb
```

## Ingressando a máquina no domínio ##

Execute o comando abaixo: 

```
net ads join -U usuarioAdministradorDoDominio
```

## Configurando Apache ##

Instale o módulo **auth_ntlm_winbind**. Para isso, copie o arquivo **utils/mod_auth_ntlm_winbind.e17.x_86_64.rpm** para o servidor e execute o comando:

```
yum localinstall mod_auth_ntlm_winbind.e17.x_86_64.rpm
```

Mova o arquivo de configuração para o local correto:

```
mv /etc/httpd/conf.d/auth_ntlm_winbind.conf ../conf.modules.d
```

Edite o arquivo `/etc/httpd/conf/httpd.conf`, habilite o **keepAlive** e adicione o módulo de autenticação à pasta do Wordpress. 
Se o Wordpress estiver instalado na pasta `/var/www`, a configuração necessária seria:

```ApacheConf
keepAlive On

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

Reinicie o Apache:

```
systemctl restart httpd
```

Feito isso, basta acessar o Wordpress e habilitar o **SSO** na tela de configuração do `simple-LDAP-plugin`.

## Configurando o Firefox ##

No caso do Firefox, você deve adicionar o seu domínio como confiável. Para isso, através do 
`about:config` edite a chave `network.automatic-ntlm-auth.trusted-uris`, colocando o seu domínio na forma
`.seu.dominio`.


