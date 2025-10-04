// Enhanced SES Bounce Processor for AEIMS Platform
// Integrates with AEIMS user management and notification systems

const { DynamoDBClient, PutItemCommand, GetItemCommand } = require('@aws-sdk/client-dynamodb');
const { SESv2Client, PutSuppressedDestinationCommand } = require('@aws-sdk/client-sesv2');
const { SNSClient, PublishCommand } = require('@aws-sdk/client-sns');

const dynamoClient = new DynamoDBClient({});
const sesClient = new SESv2Client({});
const snsClient = new SNSClient({});

// Environment variables
const BOUNCE_TABLE = process.env.BOUNCE_TABLE_NAME;
const COMPLAINT_TABLE = process.env.COMPLAINT_TABLE_NAME;
const SUPPRESSION_TABLE = process.env.SUPPRESSION_TABLE_NAME;
const PROJECT_NAME = process.env.PROJECT_NAME;
const ENVIRONMENT = process.env.ENVIRONMENT;

exports.handler = async (event) => {
    console.log('Enhanced AEIMS Bounce Processor - Received event:', JSON.stringify(event, null, 2));

    const results = [];

    try {
        // Process each SNS message
        for (const record of event.Records) {
            if (record.EventSource === 'aws:sns') {
                const message = JSON.parse(record.Sns.Message);

                let result;
                if (message.notificationType === 'Bounce') {
                    result = await processBounce(message);
                } else if (message.notificationType === 'Complaint') {
                    result = await processComplaint(message);
                } else {
                    console.log('Unknown notification type:', message.notificationType);
                    continue;
                }

                results.push(result);
            }
        }

        return {
            statusCode: 200,
            body: JSON.stringify({
                message: 'AEIMS bounce processing completed successfully',
                processed: results.length,
                results: results
            })
        };
    } catch (error) {
        console.error('Error in AEIMS bounce processing:', error);

        // Send alert to AEIMS monitoring
        await sendAeimsAlert('BOUNCE_PROCESSOR_ERROR', {
            error: error.message,
            timestamp: new Date().toISOString(),
            event_summary: `Failed to process ${event.Records?.length || 0} records`
        });

        throw error;
    }
};

async function processBounce(bounceMessage) {
    console.log('Processing AEIMS bounce:', JSON.stringify(bounceMessage, null, 2));

    const bounce = bounceMessage.bounce;
    const mail = bounceMessage.mail;
    const processedEmails = [];

    // Process each bounced recipient
    for (const bouncedRecipient of bounce.bouncedRecipients) {
        const emailAddress = bouncedRecipient.emailAddress.toLowerCase();
        const bounceType = bounce.bounceType;
        const bounceSubType = bounce.bounceSubType;

        console.log(`Processing AEIMS bounce for: ${emailAddress}, Type: ${bounceType}, SubType: ${bounceSubType}`);

        // Store bounce data in DynamoDB
        await storeBounceData({
            emailAddress,
            bounceType,
            bounceSubType,
            timestamp: bounce.timestamp,
            messageId: mail.messageId,
            source: mail.source,
            diagnosticCode: bouncedRecipient.diagnosticCode,
            action: bouncedRecipient.action,
            status: bouncedRecipient.status,
            aeimsProcessed: true,
            environment: ENVIRONMENT
        });

        // Handle bounce based on type
        if (bounceType === 'Permanent') {
            // Permanent bounce - suppress immediately
            await suppressEmail(emailAddress, 'bounce', bounceSubType);

            // Update AEIMS user status if this is a known user
            await updateAeimsUserStatus(emailAddress, 'email_bounced', {
                bounceType,
                bounceSubType,
                timestamp: bounce.timestamp
            });

            console.log(`PERMANENT BOUNCE: ${emailAddress} suppressed and AEIMS user updated`);
        } else if (bounceType === 'Transient') {
            // Temporary bounce - track but don't suppress yet
            console.log(`TEMPORARY BOUNCE: ${emailAddress} logged for monitoring`);

            // Check if this email has multiple temporary bounces
            const bounceCount = await getBounceCount(emailAddress, 'Transient');
            if (bounceCount >= 3) {
                // Multiple temporary bounces - treat as permanent
                await suppressEmail(emailAddress, 'multiple_temp_bounces', bounceSubType);
                await updateAeimsUserStatus(emailAddress, 'email_suppressed_multiple_bounces', {
                    bounceCount,
                    lastBounceType: bounceType,
                    timestamp: bounce.timestamp
                });
                console.log(`MULTIPLE TEMP BOUNCES: ${emailAddress} suppressed after ${bounceCount} attempts`);
            }
        }

        processedEmails.push({
            email: emailAddress,
            type: bounceType,
            action: bounceType === 'Permanent' ? 'suppressed' : 'logged'
        });
    }

    return {
        type: 'bounce',
        processed: processedEmails.length,
        emails: processedEmails
    };
}

async function processComplaint(complaintMessage) {
    console.log('Processing AEIMS complaint:', JSON.stringify(complaintMessage, null, 2));

    const complaint = complaintMessage.complaint;
    const mail = complaintMessage.mail;
    const processedEmails = [];

    // Process each complaint
    for (const complainedRecipient of complaint.complainedRecipients) {
        const emailAddress = complainedRecipient.emailAddress.toLowerCase();

        console.log(`Processing AEIMS complaint for: ${emailAddress}`);

        // Store complaint data
        await storeComplaintData({
            emailAddress,
            timestamp: complaint.timestamp,
            messageId: mail.messageId,
            source: mail.source,
            complaintFeedbackType: complaint.complaintFeedbackType,
            aeimsProcessed: true,
            environment: ENVIRONMENT
        });

        // Always suppress complaints to maintain reputation
        await suppressEmail(emailAddress, 'complaint', complaint.complaintFeedbackType || 'spam');

        // Update AEIMS user status
        await updateAeimsUserStatus(emailAddress, 'email_complaint', {
            complaintType: complaint.complaintFeedbackType,
            timestamp: complaint.timestamp
        });

        // Send high-priority alert for complaints
        await sendAeimsAlert('EMAIL_COMPLAINT_RECEIVED', {
            emailAddress,
            complaintType: complaint.complaintFeedbackType,
            source: mail.source,
            timestamp: complaint.timestamp
        });

        processedEmails.push({
            email: emailAddress,
            type: 'complaint',
            action: 'suppressed'
        });
    }

    return {
        type: 'complaint',
        processed: processedEmails.length,
        emails: processedEmails
    };
}

async function storeBounceData(bounceData) {
    const params = {
        TableName: BOUNCE_TABLE,
        Item: {
            emailAddress: { S: bounceData.emailAddress },
            timestamp: { S: bounceData.timestamp },
            bounceType: { S: bounceData.bounceType },
            bounceSubType: { S: bounceData.bounceSubType },
            messageId: { S: bounceData.messageId },
            source: { S: bounceData.source },
            diagnosticCode: { S: bounceData.diagnosticCode || 'N/A' },
            action: { S: bounceData.action || 'N/A' },
            status: { S: bounceData.status || 'N/A' },
            aeimsProcessed: { BOOL: bounceData.aeimsProcessed },
            environment: { S: bounceData.environment },
            processedAt: { S: new Date().toISOString() }
        }
    };

    await dynamoClient.send(new PutItemCommand(params));
    console.log(`Bounce data stored for: ${bounceData.emailAddress}`);
}

async function storeComplaintData(complaintData) {
    const params = {
        TableName: COMPLAINT_TABLE,
        Item: {
            emailAddress: { S: complaintData.emailAddress },
            timestamp: { S: complaintData.timestamp },
            messageId: { S: complaintData.messageId },
            source: { S: complaintData.source },
            complaintFeedbackType: { S: complaintData.complaintFeedbackType || 'N/A' },
            aeimsProcessed: { BOOL: complaintData.aeimsProcessed },
            environment: { S: complaintData.environment },
            processedAt: { S: new Date().toISOString() }
        }
    };

    await dynamoClient.send(new PutItemCommand(params));
    console.log(`Complaint data stored for: ${complaintData.emailAddress}`);
}

async function suppressEmail(emailAddress, reason, subType) {
    // Add to our suppression list
    const suppressionParams = {
        TableName: SUPPRESSION_TABLE,
        Item: {
            emailAddress: { S: emailAddress },
            reason: { S: reason },
            subType: { S: subType || 'N/A' },
            suppressedAt: { S: new Date().toISOString() },
            environment: { S: ENVIRONMENT },
            aeimsManaged: { BOOL: true }
        }
    };

    await dynamoClient.send(new PutItemCommand(suppressionParams));

    // Also add to SES suppression list
    try {
        const sesParams = {
            EmailAddress: emailAddress,
            Reason: reason.toUpperCase().includes('BOUNCE') ? 'BOUNCE' : 'COMPLAINT'
        };

        await sesClient.send(new PutSuppressedDestinationCommand(sesParams));
        console.log(`Email ${emailAddress} added to SES suppression list`);
    } catch (error) {
        console.error(`Failed to add ${emailAddress} to SES suppression list:`, error);
        // Continue - we still have it in our DynamoDB suppression list
    }

    console.log(`Email ${emailAddress} suppressed for: ${reason}`);
}

async function getBounceCount(emailAddress, bounceType) {
    // This would query DynamoDB to count bounces for the email
    // For now, return 0 - implement based on your needs
    return 0;
}

async function updateAeimsUserStatus(emailAddress, status, metadata) {
    // This would integrate with your AEIMS user management system
    // to update user email status, disable notifications, etc.
    console.log(`AEIMS User Update: ${emailAddress} -> ${status}`, metadata);

    // TODO: Implement AEIMS user system integration
    // Example:
    // - Update user table with email_status
    // - Disable email notifications
    // - Add to internal suppression lists
    // - Update user dashboard with notification
}

async function sendAeimsAlert(alertType, data) {
    // Send alerts through AEIMS notification system
    console.log(`AEIMS Alert: ${alertType}`, data);

    // TODO: Integrate with AEIMS alert system
    // This could publish to SNS topics, send to Slack, etc.
}