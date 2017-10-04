#   Opsweekly

This application is a weekly report tracker, an on call categorisation and reporting tool, a sleep tracker, a meeting organiser and a coffee maker all in one.

The goal of Opsweekly is to both organise our team into one central place, but also help us understand and improve our on call rotations through the use of a simple on call "survey", and reporting as a result of that tracking.

Opsweekly can retrieve information from Jira, GitHub, Bitbucket, Pagerduty and so on.

Opsweekly consists of two containers: one mariadb database and the application container (Apache + PHP 5).

The Opsweekly container is built from scratch starting from Alpine Linux and Opsweekly source code (https://github.com/etsy/opsweekly).

![homepage](doc/opsweekly-home.png)

![update](doc/opsweekly-update.png)

## More info
### Please visit <https://github.com/etsy/opsweekly/blob/master/screenshots/README.md> for a guided tour of how Opsweekly works and the reports it can generate!

## Requirements
- docker
- mysql server/database

## Setup
First, set these environment variables for the application:
```
DB_HOST:                # database host url
DB_PASSWORD:            # database user
DB_USER:                # database password
EMAIL_DOMAIN_ROOT:      # domain root to send outbound emails for reports
HOSTNAME:               # the urn of the site (i.e domain name without the protocol)
PAGERDUTY_API_KEY=      # the read-only api key you can generate from pagerduty
PAGERDUTY_BASE_URL=https://api.pagerduty.com
PAGERDUTY_TEAM_IDS=''   # comma seperated list
PAGERDUTY_INCLUDE_OPTIONS='trigger_summary_data,users,services,first_trigger_log_entries,assignees,acknowledgers,priorities'
REPORT_TO_EMAIL=''      # the email address to send the reports to
OAUTH2_CLIENT_ID=       # client id from Github
OAUTH2_CLIENT_SECRET=   # client secret from Github
PROTOCOL=http           # http or https
```

*Note*: you can copy the env.example file and edit the variables in there for local usage. Run `source <your env file name>` inside the container.

Second, setup the .htpasswd file and change the credentials to what you want:
```
cp config/apache2/htpasswd.example config/apache/htpasswd
```

Third, change the following configuration areas in `config.php` file (more information can be found [here](https://github.com/etsy/opsweekly)):
```
$weekly_providers
$oncall_providers
$sleep_providers
```

By default, I have included a custom Pagerduty weekly provider, and a Github authentication provider.

## Database
To initialize the tables in your database, run:

```
mysql> create database opsweekly;
mysql> grant all on opsweekly.* to opsweekly_user@localhost IDENTIFIED BY 'my_password';
mysql -u opsweekly_user opsweekly < opsweekly.sql
```

## Building
* To build, run:    `make build IMAGE=YOUR_DOCKER_IMAGE_NAME`
* To push, run:     `make push IMAGE=YOUR_DOCKER_IMAGE_NAME`
* To do both, run:  `make all IMAGE=YOUR_DOCKER_IMAGE_NAME`

## Accessing the site
Once done, login to opsweekly using the credentials `opsweekly:Opsw33kly!` (or whatever credentials you have changed it to)
