==========
Deployment
==========

Overview
--------

AppEcosystemV2 ExApps deployment process in short consists of 3 steps:

1. `Daemon config registration`_
2. `ExApp deployment`_
3. `ExApp registration`_


Daemon config registration
--------------------------

The first step is to register Daemon config, where your ExApps will be deployed.

.. note::
	For now only Docker daemon (`accepts-deploy-id: docker-install`) is supported.

This can be done by `occ` CLI command **app_ecosystem_v2:daemon:register**:

.. code-block:: bash

	app_ecosystem_v2:daemon:register <name> <display-name> <accepts-deploy-id> <protocol> <host> <nextcloud_url> [--net NET] [--host HOST] [--ssl_key SSL_KEY] [--ssl_key_password SSL_KEY_PASSWORD] [--ssl_cert SSL_CERT] [--ssl_cert_password SSL_CERT_PASSWORD] [--]

Arguments
*********

	* `name` - `[required]` unique name of the daemon (e.g. `docker_local_sock`)
	* `display-name` - `[required]` name of the daemon (e.g. `My Local Docker`, will be displayed in the UI)
	* `accepts-deploy-id` - `[required]` type of deployment (for now only `docker-install` is supported)
	* `protocol` - `[required]` protocol used to connect to the daemon (`unix-socket`, `network`)
	* `host` - `[required]` host of the daemon (e.g. `/var/run/docker.sock` for `unix-socket` protocol or `host:port` for `http(s)` protocol)
	* `nextcloud_url` - `[required]` Nextcloud URL, Daemon config required option (e.g. `https://nextcloud.local`)

Options
*******

	* `--net [network-name]`  - `[required]` network name to bind docker container to (default: `host`)
	* `--host HOST` - `[required]` host to expose daemon to (defaults to ExApp appid)
	* `--ssl_key SSL_KEY` - `[optional]` path to SSL key file (local absolute path)
	* `--ssl_password SSL_PASSWORD` - `[optional]` SSL key password
	* `--ssl_cert SSL_CERT` - `[optional]` path to SSL cert file (local absolute path)
	* `--ssl_cert_password SSL_CERT_PASSWORD` - `[optional]` SSL cert password

Deploy config
*************

Deploy config is a set of additional options in Daemon config, which are used in deployment algorithms to configure
ExApp container.

.. code-block:: json

	{
		"net": "nextcloud",
		"host": null,
		"nextcloud_url": "https://nextcloud.local",
		"ssl_key": "/path/to/ssl/key.pem",
		"ssl_key_password": "ssl_key_password",
		"ssl_cert": "/path/to/ssl/cert.pem",
		"ssl_cert_password": "ssl_cert_password"
	}


Deploy config options
"""""""""""""""""""""

	* `net` - `[required]` network name to bind docker container to (default: `host`)
	* `host` - `[optional]` in case Docker is on remote host, this should be a hostname of remote machine
	* `nextcloud_url` - `[required]` Nextcloud URL (e.g. `https://nextcloud.local`)
	* `ssl_key` - `[optional]` path to SSL key file (local absolute path)
	* `ssl_key_password` - `[optional]` SSL key password
	* `ssl_cert` - `[optional]` path to SSL cert file (local absolute path)
	* `ssl_cert_password` - `[optional]` SSL cert password

.. note::
	Common configurations are tested by CI in our repository, see `workflow on github <https://github.com/cloud-py-api/app_ecosystem_v2/blob/main/.github/workflows/tests-deploy.yml>`_

Example
*******

Example of `occ` **app_ecosystem_v2:daemon:register** command:

.. code-block:: bash

	sudo -u www-data php occ app_ecosystem_v2:daemon:register docker_local_sock "My Local Docker" docker-install unix-socket /var/run/docker.sock "https://nextcloud.local" --net nextcloud


ExApp deployment
----------------

Second step is to deploy ExApp on registered daemon.
This can be done by `occ` CLI command **app_ecosystem_v2:app:deploy**:

.. code-block:: bash

	app_ecosystem_v2:app:deploy <appid> <daemon-config-name> [--info-xml INFO-XML] [-e|--env ENV] [--]

.. warning::
	After successful deployment (pull, create and start container), there is a heartbeat check with 1 hour timeout (will be configurable).
	If command seems to be stuck, check if ExApp is running and accessible by Nextcloud instance.

.. note::
	For development this step is skipped, as ExApp is deployed and started manually by developer.

Arguments
*********

	* `appid` - `[required]` unique name of the ExApp (e.g. `app_python_skeleton`, must be the same as in `info.xml`)
	* `daemon-config-name` - `[required]` unique name of the daemon (e.g. `docker_local_sock`)

Options
*******

	* `--info-xml INFO-XML` - `[required]` path to info.xml file (url or local absolute path)
	* `-e|--env ENV` - `[optional]` additional environment variables (e.g. `-e "MY_VAR=123" -e "MY_VAR2=456"`)

Deploy result JSON output
*************************

Example of deploy result JSON output:

.. code-block::

	{
		"appid": "app_python_skeleton",
		"name":"App Python Skeleton",
		"daemon_config_name": "local_docker_sock",
		"version":"1.0.0",
		"secret":"***generated-secret***",
		"host":"app_python_skeleton",
		"port":"9001",
		"system_app": true
	}

This JSON output is used in ExApp registration step.

Deploy env variables
********************

Deploy env variables are used to configure ExApp container.
The following env variables are required and built automatically:

	* `AE_VERSION` - AppEcosystemV2 version
	* `APP_SECRET` - generated shared secret used for AppEcosystemV2 authentication
	* `APP_ID` - ExApp appid
	* `APP_VERSION` - ExApp version
	* `APP_HOST` - host ExApp is listening on
	* `APP_PORT` - port ExApp is listening on (randomly selected by AppEcosystemV2)
	* `NEXTCLOUD_URL` - Nextcloud URL to connect to

.. note::
	additional envs can be passed using multiple `--env ENV_NAME=ENV_VAL` options)

Docker daemon remote
********************

If you want to connect to remote docker daemon with TLS enabled, you need to provide SSL key and cert by provided options.
Important: before deploy you need to import ca.pem file using occ command:

```
php occ security:certificates:import /path/to/ca.pem
```

The daemon must be configured with `protocol=http|https`, `host=https://dockerapihost`, `port=8443`.
More info about how to configure daemon will be added soon.

ExApp registration
------------------

Final step is to register ExApp in Nextcloud.
This can be done by `occ` CLI command **app_ecosystem_v2:app:register**:

.. code-block:: bash

	app_ecosystem_v2:app:register <deploy-json-output> [-e|--enabled] [--force-scopes] [--]

Arguments
*********

	* `deploy-json-output` - `[required]` JSON output from deploy step

Options
*******

	* `-e|--enabled` - `[optional]` enable ExApp after registration
	* `--force-scopes` - `[optional]` force scopes approval


This step can be combined with deployment step into one command:

.. code-block:: bash

	sudo -u www-data php occ app_ecosystem_v2:app:register "$(sudo -u www-data php occ app_ecosystem_v2:app:deploy app_python_skeleton docker_local_sock --info-xml https://raw.githubusercontent.com/cloud-py-api/py_app_v2-skeleton/main/appinfo/info.xml)" --enabled --force-scopes


ExApp info.xml schema
---------------------

ExApp info.xml (`example repo <https://github.com/cloud-py-api/py_app_v2-skeleton>`_) file is used to describe ExApp params.
It is used to generate ExApp docker container and to register ExApp in Nextcloud.
It has the same structure as Nextcloud appinfo/info.xml file, but with some additional fields:

.. code-block:: xml

	...
	<ex-app>
		<docker-install>
			<registry>ghcr.io</registry>
			<image>cloud-py-api/py_app_v2-skeleton</image>
			<image-tag>latest</image-tag>
		</docker-install>
		<protocol>http</protocol>
		<system>0</system>
	</ex-app>
	...
