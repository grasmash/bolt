#!/bin/bash
# Common functions for cloud hooks.

status=0

drush_alias=${site}'.'${target_env}

acsf_deploy() {
  sites=()
  # Prep for BLT commands.
  repo_root="/var/www/html/$site.$target_env"
  export PATH=$repo_root/vendor/bin:$PATH
  cd $repo_root

  echo "Running updates for environment: $target_env"

  # Generate an array of all site URIs on the Factory from parsed output of Drush utility.
  while IFS=$'\n' read -r line; do
      sites[i++]="$line"
      done < <(drush @"${drush_alias}" --include=./drush acsf-tools-list | grep domains: -A 1 | grep 0: | sed -e 's/^[0: ]*//')
      unset IFS

  # Loop through each available site uri and run BLT deploy updates.
  for uri in "${sites[@]}"; do
  #Override BLT default deploy uri.
  blt deploy:update -D drush.uri "$uri" -v
  if [ $? -ne 0 ]; then
      echo "Update errored for site $uri."
      exit 1
  fi

  echo "Finished updates for site: $uri."
  done

  echo "Finished updates for all $target_env sites."
}
