
**Usage**: %phive [global-options] <command> [arguments]

**Global options:**
    _--home_         Set a custom Phive home directory (default: ~/.phive)
    _--no-progress_  Do not print progress updates during file downloads

**Commands:**

**help**
    Show this help output and exit

**version**
    Show release version and exit

**list**
    Show a list of PHAR aliases found in $PHIVE_HOME/repositories.xml

**install [--target bin/] <alias|url> [<alias|url> ...]**
    Perform installation of a phar distributed application or library

    _alias/url_                    Installation via github profile/project, gitlab profile/project,
                                 phar.io alias or explicit download form given URL

    _-t, --target_                 Set custom target directory for the PHAR

    _-c, --copy_                   Copy PHAR file instead of using symlink
    _-e, --extension_              Add extension ".phar" to installed file. It can be usefull for autocompletion by IDE
                                   in configuration files of installed tools.
    _-g, --global_                 Install a copy of the PHAR globally (likely requires root privileges)
        _--temporary_              Do not add entries in phive.xml for installed PHARs
        _--trust-gpg-keys_         Silently import these keys when required (40-digit fingerprint
                                   or 16-digit long key ID, optionally with the `0x` prefix, separated by comma)
        _--force-accept-unsigned_  Force installation of unsigned phars

**composer**
    Parse composer.json file for known aliases and suggest installation

**purge**
    Delete unused PHARs

**remove <alias>**
    Remove installed PHAR from project

**reset [<alias1> <alias2> ...]**
    Reset symlinks to PHARs used in the project.

    _alias_    If one or more aliases are provided, only those will be reset

**selfupdate**
    Update PHIVE to the latest version.

**skel [--auth]**
    Create a default configuration file

    _-a, --auth_   Create authentication configuration file

**status [--all] [--global]**
    Get a list of configured PHARs for the current directory
    
    _-a, --all_      List all downloaded PHARs and their usages across the filesystem.
    _-g, --global_   List globally installed phars

**outdated**
    Get a list of phars that are outdated and could be updated

**migrate [--status]**
    Run Phive migration
    
    _-s, --status_   Show the status of all migrations

**update [--prefer-offline] [<alias1> <alias2> ...]**
    Update PHARs configured in the project's phive.xml to the newest applicable version.

    _--prefer-offline_    Try to use local PHARs first, only connect to remote repositories
                        if no local PHAR satisfies the given version constraint.

    _alias_               If one or more aliases are provided, only those will be updated

**update-repository-list**
    Update the alias list of known PHAR distributed applications and libraries

