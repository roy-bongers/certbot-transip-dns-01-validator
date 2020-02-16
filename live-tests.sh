#!/bin/bash
#
# !!! WARNING THIS SCRIPT WHIPES THE ENTIRE /etc/letsencrypt DIRECTORY !!!
#
# For some reason when requesting an already revoked / deleted certificate Let’s Encrypt will still be able to hand
# you a new one without doing the DNS challenge. That's not really convenient for testing. So far the easiest solution
# is to just whipe the directory.
#
# This script is used to live-test new releases. It requests a TEST certificate via:
#  - Certbot directly
#  - Docker container using config file
#  - Docker container use ENV variables

echo '!!! WARNING THIS SCRIPT WHIPES THE ENTIRE /etc/letsencrypt DIRECTORY !!!'
echo ''
read -r -p "E-mail for Let’s Encrypt: " EMAIL
read -r -p "Domain to request a TEST certificate for: " DOMAIN
read -r -p "Docker image [rbongers/certbot-dns-transip]: " DOCKER_IMAGE
echo ''

DOCKER_IMAGE=${DOCKER_IMAGE:-'rbongers/certbot-dns-transip'}

# generate docker tag for current branch
BRANCH=$(git rev-parse --abbrev-ref HEAD)
DOCKER_TAG=${BRANCH/\//-}
if [[ "$BRANCH" == "master" ]]; then
  DOCKER_TAG="latest"
fi

# make sure the directories are empty
sudo rm -rf "${PWD}"/letsencrypt/*
sudo rm -rf /etc/letsencrypt/*

#
# Fetch cert using certbot directly
#
echo "Requesting certificate via Certbot"
sudo certbot certonly -n --manual-public-ip-logging-ok --agree-tos -m "$EMAIL" --test-cert --manual --preferred-challenges=dns \
  --manual-auth-hook "${PWD}/auth-hook" --manual-cleanup-hook "${PWD}/cleanup-hook" \
  -d "$DOMAIN"
sudo rm -rf /etc/letsencrypt/*

# pull docker image
docker pull "rbongers/certbot-dns-transip:${DOCKER_TAG}"

if [ ! -d "${PWD}/letsencrypt" ]; then
  mkdir "${PWD}/letsencrypt"
fi


#
# run docker with config folder
#
echo "Requesting certificate via Docker using config file"
docker run -ti \
  --mount type=bind,source="${PWD}/config",target="/opt/certbot-dns-transip/config" \
  --mount type=bind,source="${PWD}/letsencrypt",target="/etc/letsencrypt" \
  "${DOCKER_IMAGE}:${DOCKER_TAG}" \
  certonly -n --manual-public-ip-logging-ok --agree-tos -m "$EMAIL" --test-cert --manual --preferred-challenge=dns \
  --manual-auth-hook=/opt/certbot-dns-transip/auth-hook \
  --manual-cleanup-hook=/opt/certbot-dns-transip/cleanup-hook \
  -d "$DOMAIN"
sudo rm -rf "${PWD}"/letsencrypt/*


#
# Run docker with env variables.
#
# Read credentials from config/config.php
export TRANSIP_LOGIN=$(php -r '$config = include("config/config.php"); echo $config["login"];')
export TRANSIP_PRIVATE_KEY=$(php -r '$config = include("config/config.php"); echo $config["private_key"];')

echo "Requesting certificate via Docker using ENV variables"
docker run -ti \
  -e TRANSIP_LOGIN -e TRANSIP_PRIVATE_KEY \
  --mount type=bind,source="${PWD}/letsencrypt",target="/etc/letsencrypt" \
  "${DOCKER_IMAGE}:${DOCKER_TAG}" \
  certonly -n --manual-public-ip-logging-ok --agree-tos -m "$EMAIL" --test-cert --manual --preferred-challenge=dns \
  --manual-auth-hook=/opt/certbot-dns-transip/auth-hook \
  --manual-cleanup-hook=/opt/certbot-dns-transip/cleanup-hook \
  -d "$DOMAIN"
sudo rm -rf "${PWD}"/letsencrypt/*

unset TRANSIP_LOGIN
unset TRANSIP_PRIVATE_KEY
