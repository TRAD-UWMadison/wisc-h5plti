echo "Computing md5 checksum of custom hypothesis boot.js"
online_hypothesis_md5=$(curl -s https://hypothesis-h5p.s3.us-east-2.amazonaws.com/boot.js | md5 | awk '{print $1}')
valid_hypothesis_md5=$(md5 hypothesis-boot-20180301.js | awk '{ print $4 }')
if [ "$valid_hypothesis_md5" == "" ]; then
    echo "Failed to get checksum for local file; this script must be run from the 'tests' directory."
    exit
fi
hypothesis_confirmation_date="2018.03.01"
if [ "$online_hypothesis_md5" == "$valid_hypothesis_md5" ]; then
    echo "Hypothesis script has been verified. (MD5=$valid_hypothesis_md5)"
else
    echo "Hypothesis script has changed since it was last verified as working on $hypothesis_confirmation_date. ($online_hypothesis_md5 does not match confirmed MD5 $valid_hypothesis_md5)"
fi
