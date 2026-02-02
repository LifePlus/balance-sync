pipeline {
    agent none
    stages {
        stage('Install PHP dependencies') {
            agent {
                docker {
                    image 'composer:2'
                    args '--pull always'
                }
            }
            steps {
                sh 'composer install -o --no-dev --no-ansi --no-scripts'
            }
        }
        stage('Deploy') {
            agent any
            steps {
                tar file: 'site.tar.gz', overwrite: true, glob: 'app/**,application,bootstrap/**,box.json,composer.json,composer.lock,config/**,storage/**,vendor/**'
                withCredentials([sshUserPrivateKey(credentialsId: 'gholle', keyFileVariable: 'SSH_KEY', usernameVariable: 'SSH_USER')]) {
                    sh '''
                        chmod 600 "$SSH_KEY"
                        scp -o StrictHostKeyChecking=no -i "$SSH_KEY" site.tar.gz "$SSH_USER"@10.100.1.127:/tmp/balance-sync.tar.gz
                        ssh -o StrictHostKeyChecking=no -i "$SSH_KEY" "$SSH_USER"@10.100.1.127 'sudo -u autocomm bash -s' << 'DEPLOY'
                            set -e
                            cd /home/autocomm/balance-sync

                            # Extract new version
                            tar -xzf /tmp/balance-sync.tar.gz -C /home/autocomm/balance-sync

                            # Clear caches
                            php application optimize:clear 2>/dev/null || true

                            # Cleanup
                            rm /tmp/balance-sync.tar.gz

                            echo "Deployment complete"
DEPLOY
                    '''
                }
            }
        }
    }
}
