const request = require('request'),
    exec = require("child_process").exec,
    moment = require("moment"),
    connection = require("./config/index"),
    CitedPatents = require("./models/CitedPatents"),
    CitingPatentWithAssignee = require("./models/CitingPatentWithAssignee"),
    AssigneeOrganizations = require("./models/AssigneeOrganizations");

const Pusher = require("pusher")

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
const organisationID = argumentSlice[0]
let companies = argumentSlice[1]
const ownedAssets = argumentSlice[2]

let getAssetsList = [], errorCodes = []


const sendNotification = (message) => {
    pusher.trigger(CHANNEL, EVENT, message)
}

const optimizeAssigneeName = (name) => {
    const regex = /\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|university|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|gesellschaft|gbr|ohg|handelsgesellschaft|compagnie|privatstiftung|foundation|cie)\b/ig

    name = name.replace(regex, '')
    name = name.replace(/\s+/, ' ')
    name = name.replace(/[,]/g, ' ')
    name = name.replace(/[.]/g, ' ')
    name = name.replace(/[!]/g, ' ')
    return name.trim();
}
 
const retrieveCitedPatentAssignee = async(startIndex) => {
    const url = `https://api.patentsview.org/patents/query?q={"cited_patent_number":"${getAssetsList[startIndex].grant_doc_num}"}&f=["patent_number","assignee_organization","app_date"]&o={"per_page":10000}`
    console.log('url', url)
    //sendNotification(`Request: ${startIndex + 1} / ${getAssetsList.length}`)
    request(url, async(error, response, body) => {
        if (!error && response.statusCode == 200) {
            const responseBody = JSON.parse(body)
            console.log('responseBody', responseBody)
            const { patents, count, total_patent_count } = responseBody
            if(count > 0) {
                let allAssignees = [], tempAssignees = [], allAssigneeWithPatentNumber = []
                patents.forEach( patent => {
                    if(patent.assignees.length > 0) {
                        patent.assignees.forEach( assignee => { 
                            if(assignee.assignee_organization !== null) {
                               
                                let appDate = '0000-00-00'
                                if(patent.applications !== null && patent.applications.length > 0) {
                                    appDate = patent.applications[0].app_date
                                }
                                if(moment(new Date(appDate)).format('YYYY') > 1999) {
                                    allAssignees.push(assignee.assignee_organization)
                                    allAssigneeWithPatentNumber.push({
                                        patent_number: patent.patent_number,
                                        assignee_organization: assignee.assignee_organization,
                                        app_date: appDate,
                                        assignee_id: 0
                                    })
                                } 
                            }
                        })
                    }                    
                })
                tempAssignees = [...allAssignees]
                console.log('allAssignees', allAssignees.length, JSON.stringify(allAssignees))


                let getAllAssigneeWithIDs = await AssigneeOrganizations.findAll({
                    attributes:["assignee_id", "assignee_organization"],
                    where: {assignee_organization: allAssignees},
                    group: ["assignee_organization"]
                })

                let insertAssignees = []

                if(getAllAssigneeWithIDs.length > 0) {
                    getAllAssigneeWithIDs.forEach(row => {
                        if(allAssignees.includes(row.assignee_organization)) {
                            allAssignees = allAssignees.filter( assignee => assignee.toLowerCase() != row.assignee_organization.toLowerCase())
                        }
                    })
                } 
                if(allAssignees.length > 0){
                    // INSERT FIRST
                    allAssignees.forEach( assignee => {
                        if(assignee !== null) {
                            console.log("assignee", assignee)
                            /*const queryName = optimizeAssigneeName(assignee)*/
                            insertAssignees.push({assignee_organization: assignee, assignee_query: assignee})
                        }
                    })
                }
                //console.log('allAssignees', allAssignees.length, JSON.stringify(allAssignees))
                // console.log('insertAssignees', insertAssignees)
                if(insertAssignees.length > 0) {
                    await AssigneeOrganizations.bulkCreate(insertAssignees, { ignoreDuplicates: true })
                }


                if(insertAssignees.length > 0) {
                    getAllAssigneeWithIDs = await AssigneeOrganizations.findAll({
                        attributes:["assignee_id", "assignee_organization"],
                        where: {assignee_organization: tempAssignees},
                        group: ["assignee_organization"]
                    })
                }

                if(getAllAssigneeWithIDs.length > 0) {
                    const insertData = []
                    getAllAssigneeWithIDs.forEach( async row => {
                        insertData.push({
                            patent_number: getAssetsList[startIndex].grant_doc_num,
                            assignee_id: row.assignee_id
                        })

                        const promise =  allAssigneeWithPatentNumber.map( (assignee, index) => {
                            if(assignee.assignee_organization == row.assignee_organization) {
                                allAssigneeWithPatentNumber[index].assignee_id = row.assignee_id
                            }
                        })

                        await Promise.all(promise)
                    })

                    const getIDs = []

                    const promise = allAssigneeWithPatentNumber.map( assignee => {
                        if(assignee.assignee_id == 0) {
                            getIDs.push(assignee.assignee_id)
                        }
                    })
                    await Promise.all(promise);

                    console.log('insertData', insertData)
                    await CitedPatents.bulkCreate(insertData, { ignoreDuplicates: true })
                    console.log('allAssigneeWithPatentNumber', allAssigneeWithPatentNumber)
                    await CitingPatentWithAssignee.bulkCreate(allAssigneeWithPatentNumber, { ignoreDuplicates: true })
                    //Send notification
                    //sendNotification(`Assignees retireved`)
                    if(getIDs.length > 0) {
                        getAllAssigneeWithIDs = await AssigneeOrganizations.findAll({
                            attributes:["assignee_id", "assignee_organization"],
                            where: {assignee_id: getIDs},
                            group: ["assignee_organization"]
                        })
                        getAllAssigneeWithIDs.map( assignee => {
                            exec(`node /var/www/html/script/name_to_domain_api.js "${organisationID}" "rapidapi" '[${assignee.assignee_id}]' 0 '${companies}' 2 '${ownedAssets}' `, (error, stdd, stderr)=> {
                                console.log("fill name_to_domain_api.js ....")
                                console.log(error); 
                                console.log(stderr);
                                console.log(stdd);
                                console.log("DONE");
                            });
                        })
                    }
                    checkNextRow(startIndex)
                } else {
                    checkNextRow(startIndex)
                }  
            } else {
                checkNextRow(startIndex)
            }
        } else {
            errorCodes.push(getAssetsList[startIndex].grant_doc_num)
            checkNextRow(startIndex)
        }
    })
}

const checkNextRow = (startIndex) => {
    startIndex += 1
    if(startIndex < getAssetsList.length) {
        retrieveCitedPatentAssignee(startIndex)
    } else {
        if(errorCodes.length > 0) {
            sendNotification(`Find error from API in these patents: ${errorCodes.join(', ')}`)
        }
        sendNotification(`Cited Patents finished.`)
    }
}

const getAssetsLists = async (organisationID, companies, ownedAssets) => {
   
    try {
        
        let queryAllPatents = `SELECT grant_doc_num FROM assets WHERE date_format(appno_date, '%Y') > 1999 AND grant_doc_num <> '' AND organisation_id = :organisationID `
        const replacements = {organisationID}
        if(companies !== '' && companies !== null && companies !== undefined) {
            companies = JSON.parse(companies)
            replacements.companies = companies
            if( companies.length > 0 ) {
                queryAllPatents += ` AND company_id IN (:companies)`
            }
        }
        console.log(typeof ownedAssets, ownedAssets)
        if(typeof ownedAssets != 'undefined' && ownedAssets == 1) {
            let queryOwnedAssets = `SELECT application FROM db_new_application.dashboard_items WHERE type = 30 AND organisation_id = :organisationID `

            if(typeof companies !== 'undefined' && companies.length > 0) {
                queryOwnedAssets += ` AND representative_id IN (:companies) `
            }

            queryOwnedAssets += ` GROUP BY application`

            const getList = await connection.application.query(queryOwnedAssets,{
                    type: connection.Sequelize.QueryTypes.SELECT,
                    raw: true,
                    replacements,
                    logging: console.log,
                }
            );
            if(getList.length > 0) {
                const allOwnedAssets = []

                const promise = getList.map(asset => {
                    allOwnedAssets.push(`${asset.application}`)
                })

                await Promise.all(promise)

                if(allOwnedAssets.length > 0) {
                    replacements.assets = allOwnedAssets
                    queryAllPatents += ` AND appno_doc_num IN (:assets)`
                }
            }
        }
        /*queryAllPatents += ` AND grant_doc_num NOT IN (SELECT patent_number FROM cited_patents GROUP BY patent_number) GROUP BY grant_doc_num`*/
        queryAllPatents += `  GROUP BY grant_doc_num`
        
        //console.log('connection', connection)
        let list = await connection.application.query(queryAllPatents,{
            type: connection.Sequelize.QueryTypes.SELECT,
            raw: true,
            logging: console.log,
            replacements
            }
        );

        console.log('list', list)
        return list

    } catch (err) {
        return []
    }
    
}


( async () => {     
    console.log('organisationID, companies, ownedAssets', organisationID, companies, ownedAssets)
    getAssetsList = await getAssetsLists(organisationID, companies, ownedAssets)
    console.log('getAssetsList.length', getAssetsList.length)
    if( getAssetsList.length > 0 ) {
        //sendNotification(`Total patents: ${getAssetsList.length}`)
        retrieveCitedPatentAssignee(0)
    }  else {
        if( typeof ownedAssets != 'undefined' && ownedAssets == '1') {
            let companyIDs = []
            if(companies !== '' && companies !== null && companies !== undefined) {
                companyIDs = JSON.parse(companies) 
            }
            exec(`php -f /var/www/html/trash/dashboard_with_company.php ${companyIDs.length > 0 ? companyIDs[0] : ''} ${organisationID}`, async(err, stdout, stderr) => {
                console.log(`php -f /var/www/html/trash/dashboard_with_company.php ${companyIDs.length > 0 ? companyIDs[0] : ''} ${organisationID}`)
                console.log('stdout', stdout)
                console.log('stderr', stderr)
                if (err) {
                    console.error(`exec error: ${err}`);
                    return;
                }
                getAssetsList = await getAssetsLists(organisationID, companies, ownedAssets) 
                console.log('getAssetsList.length', getAssetsList.length)
                if( getAssetsList.length > 0 ) {
                    retrieveCitedPatentAssignee(0) 
                }
            });
         }
    }
})();