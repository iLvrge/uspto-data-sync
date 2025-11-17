const request = require('request'),
    process = require('process'),
    connection = require("./config/index"),
    CitedPatents = require("./models/CitedPatents"),
    AssigneeOrganizations = require("./models/AssigneeOrganizations");

const { createLogger, format, transports } = require("winston");

const Pusher = require("pusher");

const clearbit = require('clearbit')('sk_d89c1b8d6af056c47526bc5efa4ae544');
const RITEKIT_CLIENTID = `9e44da1127bae5aee46bb12723f7dada36d3ae76916d`
const UPLEAD_CLIENTID = `977c4d4eaa39794b7ee53b4d8da026b1`




const logger = createLogger({
    format: format.combine(format.timestamp(), format.json()),
    transports: [new transports.File({ filename: "/var/www/html/trash/name_to_domain_api.log" })],
    exceptionHandlers: [new transports.File({ filename: "/var/www/html/trash/name_to_domain_api_exceptions.log" })],
    rejectionHandlers: [new transports.File({ filename: "/var/www/html/trash/name_to_domain_api_rejections.log" })],
});

const APPID='938985'
const KEY='3252bb191d77e92ddb3c'
const SECRET='2a3dd823cd1abcd45c71'
const CLUSTER='us3'
const USETLS=true
const CHANNEL='patentrack-channel'
const EVENT='patentrack-event'

const pusher = new Pusher({
    appId: APPID,
    key: KEY,
    secret: SECRET,
    cluster: CLUSTER, 
    useTLS: USETLS,
    keepAlive: true,
})

const argumentSlice = process.argv.slice(2)
console.log(argumentSlice)
const organisationID = argumentSlice[0], apiName = argumentSlice[1];
let assigneeIDs = argumentSlice[2], typeRetreival = typeof argumentSlice[3] !== 'undefined' ? argumentSlice[3] : null, companyID = typeof argumentSlice[4] !== 'undefined' ? argumentSlice[4] : null, all = typeof argumentSlice[5] !== 'undefined' ? argumentSlice[5] : 0;



let getAssigneeList = [], errorCodes = [], currentIndex = -1, previousIndex = -1

setInterval(() => {
	if(currentIndex !== previousIndex) {
		previousIndex = currentIndex
		sendNotification(`Logo fetch ${currentIndex} / ${getAssigneeList.length}`)
	}
}, 60000)


const sendNotification = (message) => {
    pusher.trigger(CHANNEL, EVENT, message)
}

const NameToDomain  = clearbit.NameToDomain;

const retrieveCitedPatentAssignee = async(startIndex) => {
    let name = getAssigneeList[startIndex].assignee_query
    
    console.log('getAssigneeList[startIndex].assignee_organization', name, apiName)
    switch (apiName) {
        case 'clearbit':
            clearbitAPI(name, startIndex)
            break;
        case 'uplead':
            upleadAPI(name, startIndex)
        break;
        case 'rapidapi':
            //rapidAPI(startIndex)
            rapidAPIUpdate()
        break;
        case 'ritekit':
            typeRetreival == 0 ? ritekitAPI(name, startIndex) : runRitekitLogo(startIndex)
        break;
    }
    
}

const updateAssigneeData = async (params, assignee_id) => {
    console.log(`UPDATE START ${assignee_id}`)
    await AssigneeOrganizations.update(params, {
        where: {
            assignee_id
        }
    })
    console.log(`UPDATE END ${assignee_id}`)
    
    sendNotification(`IMAGES_RETRIEVED: ${assignee_id}`)
}


const sendRequest = (url, headers) => {
    console.log(`REQUESTED URL: ${url}`)
    const promise = new Promise((resolve, reject) => {
        const  request = require('request');
        let options = {
            method: 'GET',
            url,
            headers
        };
        request(options, function (error, response) {
            if (!error){
                resolve(response)
            } else {
                reject(error)
            }
        })
    })
    return promise
}


const requestRapid = async (searchItem, type) => {
    const patternMatch = '\\b(?:inc|llc|corporation|corp|llp|gmbh|lp|sas|na|co|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|ab|sa)\\b'; 
            
    let searchString = searchItem
    /* console.log("requestRapid", searchString, type) */
    if(type == 1) {
        const regex = new RegExp(patternMatch,"gi")
        searchString = searchString.replace(regex, "")  
        searchString = searchString.replace(/\//g, ' ')  
        searchString = searchString.replace(/\./g, '')  
        searchString = searchString.replace(/\,/g, '') 
    }  
    searchString = searchString.replace(/-/g, ' ') 
    searchString = searchString.replace(/—/g, ' ') 
    searchString = searchString.trim() 
    searchString = searchString.replace(/ /g,"%20") 
    searchString = searchString.replace(/&/g, 'and') 
   /*  console.log('searchString', searchString) */
    /*  let query = `%22${searchString}%22`; */
    let query = `${searchString.trim()} +logo `;
    const logoURL = `https://google-search3.p.rapidapi.com/api/v1/image/q=${query}`
    /* console.log(logoURL)
    logger.info(logoURL) */
    const response = await sendRequest(logoURL, {
        'x-user-agent': 'desktop',
        'x-proxy-location': 'US',
        'x-rapidapi-host': 'google-search3.p.rapidapi.com',
        'x-rapidapi-key': 'e9431999femshbd54071785cc02bp151695jsnc5d122698e5d'
    })
    return response
}

const rapidAPIUpdate = async() => {
    await getAssigneeList.reduce(async (promise, row, currentIndex) => {
        
        await promise;
        try{
            let searchString = row.assignee_query ;
            const response = await requestRapid(searchString, 2);
            console.log('asdadasdadasdsadasddasdasd', response, response.body);
            /* logger.info(response)
            console.log(response) */
            console.log(response.body)
            if(response !== null) {
                try{ 
                    const {image_results, message} = JSON.parse(response.body);
                    console.log('FIRST', image_results.length, image_results)
                    if(typeof image_results !== 'undefined') {
                        if( image_results.length > 0) {
                            const updateFields = {}
                            for(let i = 0; i < 5; i++) {
                                if(typeof image_results[i] !== 'undefined' && typeof image_results[i].image !== 'undefined') {
                                    updateFields[`api_logo${i > 0 ? i : ''}`] = image_results[i].image.src
                                }
                            }

                            const responseSecond = await requestRapid(searchString, 1);

                           /*  logger.info(responseSecond)
                            console.log(responseSecond) */

                            if(responseSecond !== null) {
                                let {image_results, message} = JSON.parse(responseSecond.body);
                                console.log('SECOND', image_results.length, image_results)
                                if( image_results.length > 0) { 
                                    console.log('In second request')
                                    let inc = 5;
                                    for(let i = 0; i < 5; i++) {
                                        if(typeof image_results[inc] !== 'undefined' && typeof image_results[i].image !== 'undefined') {
                                            updateFields[`api_logo${inc > 0 ? inc : ''}`] = image_results[i].image.src
                                        }
                                        inc++;
                                    }
                                }
                            }   

                            console.log(`UPDATE INDEX: ${currentIndex}`, updateFields)
                            await updateAssigneeData(updateFields, row.assignee_id)
                            console.log(console.log(`UPDATE FINISHED ${row.assignee_id}`))
                        }                    
                    } else if (typeof message !== 'undefined') {
                        console.log(`error from API: ${message}`)
                        sendNotification(message)
                    }                    
                } catch (e) {
                    console.log('Error in rapidAPI', e)
                    sendNotification(e.message)
                }
            } else {
                console.log('No response from API')
                sendNotification('No response from rapid API')
            }
        } catch (e) {
            console.log(e.message)
        }        
    }, Promise.resolve());
    console.log('DONE')
	sendNotification(`Cited Patents finished.`)
    process.kill(process.pid, 'SIGINT');
	/*setTimeout(() => {
		process.exit(0);
	}, 2000)*/
}


const rapidAPI = async(startIndex) => {
    try {
        if(getAssigneeList[startIndex].assignee_query !== '' && getAssigneeList[startIndex].assignee_query !== null) {
			currentIndex = startIndex
			console.log(`Current index running ${currentIndex}`)
            let assigneeName = getAssigneeList[startIndex].assignee_query 
            assigneeName = assigneeName.replace(/&/g, 'and')
            let query = `%22${encodeURIComponent(assigneeName)}%22+logo`;
            const logoURL = `https://google-search3.p.rapidapi.com/api/v1/images/q=${query}`
            console.log('logoURL', logoURL)
            const {response} = await sendRequest(logoURL, {
                'x-user-agent': 'desktop',
                'x-proxy-location': 'US',
                'x-rapidapi-host': 'google-search3.p.rapidapi.com',
                'x-rapidapi-key': 'e9431999femshbd54071785cc02bp151695jsnc5d122698e5d'
            })

            if(response !== null) {
                try{ 
                    const {image_results, message} = JSON.parse(response.body);
                    if(typeof image_results !== 'undefined') {
                        if( image_results.length > 0) {
                            const updateFields = {}
                            for(let i = 0; i < 10; i++) {
                                if(typeof image_results[i] !== 'undefined' && typeof image_results[i].image !== 'undefined') {
                                    updateFields[`api_logo${i > 0 ? i : ''}`] = image_results[i].image.src
                                }
                            }
                            console.log(`UPDATE INDEX: ${startIndex}`)
                            await updateAssigneeData(updateFields, getAssigneeList[startIndex].assignee_id)
                        }     
                        checkNextRow(startIndex)                   
                    } else if (typeof message !== 'undefined') {
                        console.log(`error from API: ${message}`)
                        sendNotification(message)
                    } else {
                        console.log(`no image_results no message`)
                        checkNextRow(startIndex)         
                    }                    
                } catch (e) {
                    console.log('Error in rapidAPI', e)
                    checkNextRow(startIndex)
                }
            }
        }
    } catch (e) {
        console.log(e.message)
        sendNotification(e.message)
        checkNextRow(startIndex)
    }
}


const checkNextRow = (startIndex) => {
    startIndex += 1
	console.log(`checkNextRow : ${startIndex} - ${getAssigneeList.length}`)
    if(startIndex < getAssigneeList.length) {
        retrieveCitedPatentAssignee(startIndex)
    } else {
        if(errorCodes.length > 0) {
            sendNotification(`Find error from API in these patents: ${errorCodes.join(', ')}`)
        }
        sendNotification(`Cited Patents finished.`)
    }
}

const optimizeAssigneeName = (name) => {
    const regex = /\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|university|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|gesellschaft|gbr|ohg|handelsgesellschaft|compagnie|privatstiftung|foundation|sa)\b/ig

    name = name.replace(regex, '')
    name = name.replace(/\s+/, ' ')
    name = name.replace(/[,]/g, ' ')
    name = name.replace(/[.]/g, ' ')
    name = name.replace(/[!]/g, ' ')
    return name.trim();
}


( async () => {    
    const replacements = {organisationID, assigneeIDs: []}
    let queryCitingAssignees = `SELECT COUNT(ao.assignee_id) AS occurences, ao.assignee_id, ao.assignee_organization, ao.assignee_query, ao.domain, ao.domain2, ao.domain3, ao.api_logo FROM assignee_organizations AS ao
                            INNER JOIN cited_patents AS cp ON cp.assignee_id = ao.assignee_id
                            WHERE cp.patent_number IN (SELECT grant_doc_num COLLATE utf8mb4_general_ci FROM assets WHERE organisation_id = :organisationID `
    if(organisationID == 0) {
        queryCitingAssignees = `SELECT COUNT(ao.assignee_id) AS occurences, ao.assignee_id, ao.assignee_organization, ao.assignee_query, ao.domain, ao.domain2, ao.domain3, ao.api_logo FROM assignee_organizations AS ao
        WHERE  organisation_id = 0  `
    }
                            
    if(companyID !== null && companyID != '') {
        const companyIDs = JSON.parse(companyID)
        if(companyIDs.length > 0) {
            replacements.companyIDs = companyIDs
            queryCitingAssignees += ` AND company_id IN (:companyIDs)  `
        }
    }

    if(organisationID != 0) {
        queryCitingAssignees += ` AND grant_doc_num <> '' GROUP BY grant_doc_num) AND organisation_id = 0`
    }
    //console.log('connection', connection)


    if(assigneeIDs !== '' && assigneeIDs !== null && assigneeIDs !== undefined) {
        assigneeIDs = JSON.parse(assigneeIDs)
        replacements.assigneeIDs = assigneeIDs
		if(assigneeIDs.length > 0) {
			queryCitingAssignees += ` AND ao.assignee_id IN (:assigneeIDs) `
		}
    }
    if(replacements.assigneeIDs.length == 0 && all == 0) {
        queryCitingAssignees += `  AND (ao.api_logo IS NULL OR ao.api_logo = '' ) `
    }


    queryCitingAssignees += ` GROUP BY ao.assignee_organization  ORDER BY occurences DESC`

    
    getAssigneeList = await connection.application.query(queryCitingAssignees,{
            type: connection.Sequelize.QueryTypes.SELECT,
            raw: true,
            logging: console.log,
            replacements: replacements,
        }
    );
    console.log('getAssignees.length', getAssigneeList.length)
    if( getAssigneeList.length > 0 ) {
        //sendNotification(`Total companies: ${getAssigneeList.length}`)
        retrieveCitedPatentAssignee(0) 
        /* getAssigneeList.forEach( assignee => {
            const query = optimizeAssigneeName(assignee.assignee_organization)
            AssigneeOrganizations.update({
                assignee_query: query
            },{
                where: {
                    assignee_id: assignee.assignee_id
                }
            })
        })
         */

    }       
})();



