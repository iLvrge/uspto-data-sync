const request = require('request'),
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


let getAssetsList = [], errorCodes = [], getAssigneeOrgist = []


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
    const url = `https://api.patentsview.org/patents/query?q={"cited_patent_number":"${getAssetsList[startIndex].patent_number}"}&f=["patent_number","assignee_organization","app_date"]&o={"per_page":10000}`
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
                                allAssignees.push(assignee.assignee_organization)
                                let appDate = '0000-00-00'
                                console.log(patent.applications)
                                if(patent.applications !== null && patent.applications.length > 0) {
                                    appDate = patent.applications[0].app_date
                                }
                                allAssigneeWithPatentNumber.push({
                                    patent_number: getAssetsList[startIndex].patent_number,
                                    citing_patent_number: patent.patent_number,
                                    assignee_organization: assignee.assignee_organization,
                                    app_date: appDate,
                                    assignee_id: 0
                                })
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

                if(getAllAssigneeWithIDs.length > 0) {
                    getAllAssigneeWithIDs.forEach(row => {
                        const findIndex = allAssigneeWithPatentNumber.findIndex( assignee => assignee.assignee_organization == row.assignee_organization)

                        if(findIndex !== -1) {
                            allAssigneeWithPatentNumber[findIndex].assignee_id = row.assignee_id
                        }
                    })
                    console.log('allAssigneeWithPatentNumber', allAssigneeWithPatentNumber)
                    await CitingPatentWithAssignee.bulkCreate(allAssigneeWithPatentNumber, { ignoreDuplicates: true })
                    //Send notification
                    //sendNotification(`Assignees retireved`)
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
    /* startIndex += 1
    if(startIndex < getAssetsList.length) {
        retrieveCitedPatentAssignee(startIndex)
    } else {
        if(errorCodes.length > 0) {
            sendNotification(`Find error from API in these patents: ${errorCodes.join(', ')}`)
        }
        sendNotification(`Cited Patents finished.`)
    } */
}

const updateAssignee = async(startIndex) => {
    let getData = await AssigneeOrganizations.findOne({
        attributes:["assignee_id", "assignee_organization"],
        where: {assignee_organization: getAssigneeOrgist[startIndex]} 
    })

    if(getData != null) {
        await CitingPatentWithAssignee.update({assignee_id: getData.get('assignee_id')}, { where: { assignee_organization: getData.get('assignee_organization')} })
    }
    updateNextRow(startIndex)
}

const updateNextRow = (startIndex) => {
    startIndex += 1
    if(startIndex < getAssigneeOrgist.length) {
        updateAssignee(startIndex)
    } 
}

( async () => {    
    /* let queryAllPatents = `SELECT patent_number, assignee_id from db_new_application.cited_patents WHERE patent_number NOT IN (SELECT patent_number FROM db_new_application.citing_patents_with_assignee GROUP BY patent_number) GROUP BY patent_number `
    
        //console.log('connection', connection)
    getAssetsList = await connection.application.query(queryAllPatents,{
        type: connection.Sequelize.QueryTypes.SELECT,
        raw: true,
        logging: console.log,
        replacements: { }
        }
    );
    console.log('getAssetsList.length', getAssetsList.length)
    if( getAssetsList.length > 0 ) {
        //sendNotification(`Total patents: ${getAssetsList.length}`)
        retrieveCitedPatentAssignee(0)
    }     */

    let queryAllPatents = `SELECT assignee_organization, assignee_id FROM db_new_application.citing_patents_with_assignee WHERE assignee_id = :assignee_id  GROUP BY assignee_organization `
    getAssigneeOrgist = await connection.application.query(queryAllPatents,{
            type: connection.Sequelize.QueryTypes.SELECT,
            raw: true,
            logging: console.log,
            replacements: {assignee_id: 0 }
        }
    );

    if(getAssigneeOrgist.length > 0) {
        updateAssignee(0)
    }
})();