const fs = require('fs'),
    path = require('path'),    
    parser = require('fast-xml-parser'),
    moment = require('moment');

const { exec, spawn  } = require('child_process');
const { decode } = require('html-entities');



const ApplicantsGrant = require("./models/ApplicantsGrant"),
AssigneeGrant = require("./models/AssigneeGrant");

const extraDiskFolderPath = process.env.EXTRA_DISK_PATH;
let allFiles = []

const processXMLFiles = new Promise( function(resolve, reject) {
    const argumentSlice = process.argv.slice(2);
    const year = argumentSlice[0]
    const child = spawn('find', [`${extraDiskFolderPath}patent/XML3`, '-name', `*-${year}*.XML`]);
    child.stdout.on('data', (data) => {
        //console.log(`child.stdout: ${data}`)
        const files = data.toString().split('\n')
        if(files[files.length - 1] == '') {
            files.splice(files.length - 1, 1)
        }
        //console.log(`child.files: ${files.length}`)
        allFiles = [...allFiles, ...files]
    });
    child.stderr.on('data', (data) => {
        console.log(`ERRORRRRRRR - ${data}`)
        reject(data)
    });
    child.on('close', (code) => {
        resolve('AllFolderDOne')    
    })
})
  


Promise.all([processXMLFiles])
.then(async () => {
    console.log(`Files Length -  ${allFiles.length}`)
    allFiles = [...new Set(allFiles)];
    console.log(`Unique Files Length -  ${allFiles.length}`)
    console.log(`${allFiles[0]}`)
    if(allFiles.length > 0) {
        await readFile(0)
    } 
})


const readFile = async (fileIndex) => {
    const fullPath = allFiles[fileIndex]
    console.log(`FileIndex - ${fileIndex} - ${fullPath}`)
    const fileName = fullPath.split('/').pop();
    fs.readFile(fullPath, async function(err,data){
        if (!err) {
            try {            
                let  assignees = [], applicant = []

                const promise = new Promise( (resolve, reject) => {
                    let findIndex = data.toString().indexOf('<us-patent-grant')
                    if(findIndex !== -1) {
                        let xmlData = data.toString().substring(findIndex, data.length)
                        if(parser.validate(xmlData)) {                        
                            const xmlToJSON = parser.parse( xmlData, {ignoreAttributes: false});
                            
                            if(xmlToJSON.hasOwnProperty('us-patent-grant')) {                                 
                                const usBibliographic = xmlToJSON['us-patent-grant']['us-bibliographic-data-grant']
                                const publicationReference = usBibliographic['publication-reference']
                                const applicationReference = usBibliographic['application-reference']
                                
                                let originalName = '', address1 = '', address2 = '', city = '', state = '', postalcode = '', country = '', familyName = '', givenName = '', middleName = '', type = 0;

                                if(usBibliographic.hasOwnProperty('parties')) {
                                    const applicants = usBibliographic.parties.applicants.applicant
                                    
                                    if(Array.isArray(applicants) && applicants.length > 0) {
                                        applicants.forEach( applicant => {
                                            if(applicant['@_app-type'] == 'applicant') {
                                                const addressBook = applicant.addressbook

                                                if(addressBook.hasOwnProperty('orgname')) {
                                                    originalName = addressBook['orgname']
                                                }
                                                if(addressBook.hasOwnProperty('address')) {
                                                    const address = addressBook['address']
                                                    if(originalName != '') {
                                                        address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                        address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                        city = address.hasOwnProperty('city') ? address['city'] : ''
                                                        state = address.hasOwnProperty('state') ? address['state'] : ''
                                                        postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                        country = address.hasOwnProperty('country') ? address['country'] : ''
                                                    }
                                                }
                                                if(addressBook.hasOwnProperty('first-name') || addressBook.hasOwnProperty('last-name')) {
                                                    console.log('family', addressBook['first-name'])
                                                    familyName = addressBook.hasOwnProperty('last-name') ? addressBook['last-name'] : '';
                                                    givenName = addressBook.hasOwnProperty('first-name') ? addressBook['first-name'] : '';
                                                    middleName = addressBook.hasOwnProperty('middle-name') ?  addressBook['middle-name'] : '';
                                                    type = 1;
                                                }
                                                applicant.push({
                                                    appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                    publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],
                                                    original_name: originalName,
                                                    address_1: address1,
                                                    address_2: address2,
                                                    city,
                                                    state,
                                                    postalcode,
                                                    country,
                                                    family_name: decode(familyName,{level: 'xml'}),
                                                    given_name: decode(givenName,{level: 'xml'}),
                                                    middle_name: decode(middleName,{level: 'xml'}),
                                                    type,
                                                    file_name: fileName
                                                })
                                            }
                                        })
                                    } else if(typeof applicants === "object") {
                                        if(applicants['@_app-type'] == 'applicant') {
                                            const addressBook = applicants.addressbook

                                            if(addressBook.hasOwnProperty('orgname')) {
                                                originalName = addressBook['orgname']
                                            }
                                            if(addressBook.hasOwnProperty('address')) {
                                                const address = addressBook['address']
                                                address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                city = address.hasOwnProperty('city') ? address['city'] : ''
                                                state = address.hasOwnProperty('state') ? address['state'] : ''
                                                postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                country = address.hasOwnProperty('country') ? address['country'] : ''
                                            }
                                            if(addressBook.hasOwnProperty('first-name') || addressBook.hasOwnProperty('last-name')) {
                                                console.log('family', addressBook['first-name'])
                                                familyName = addressBook.hasOwnProperty('last-name') ? addressBook['last-name'] : '';
                                                givenName = addressBook.hasOwnProperty('first-name') ? addressBook['first-name'] : '';
                                                middleName = addressBook.hasOwnProperty('middle-name') ?  addressBook['middle-name'] : '';
                                                type = 1;
                                            }
                                            applicant.push({
                                                appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],    
                                                original_name: originalName,
                                                address_1: address1,
                                                address_2: address2,
                                                city,
                                                state,
                                                postalcode,
                                                country,
                                                family_name: decode(familyName,{level: 'xml'}),
                                                given_name: decode(givenName,{level: 'xml'}),
                                                middle_name: decode(middleName,{level: 'xml'}),
                                                type,
                                                file_name: fileName
                                            })
                                        }
                                    }


                                    if( usBibliographic.hasOwnProperty('assignees')) {
                                        console.log('ASSIGNEEEESSSSS')
                                        const assigneeList = usBibliographic.assignees.assignee
                                    
                                        if(Array.isArray(assigneeList) && assigneeList.length > 0) {
                                            console.log('ASSIGNEE', JSON.stringify(assigneeList))
                                            assigneeList.forEach( applicant => {
                                                let addressBook = ''
                                                if(applicant.hasOwnProperty('addressbook')) {
                                                    addressBook = applicant.addressbook
                                                } else {
                                                    addressBook = applicant
                                                }
                                                if(addressBook.hasOwnProperty('orgname')) {
                                                    originalName = addressBook['orgname']
                                                }
                                                if(addressBook.hasOwnProperty('address')) {
                                                    const address = addressBook['address']
                                                    address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                    address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                    city = address.hasOwnProperty('city') ? address['city'] : ''
                                                    state = address.hasOwnProperty('state') ? address['state'] : ''
                                                    postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                    country = address.hasOwnProperty('country') ? address['country'] : ''
                                                }
                                                assignees.push({
                                                    appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                    publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],
                                                    original_name: originalName,
                                                    address_1: address1,
                                                    address_2: address2,
                                                    city,
                                                    state,
                                                    postalcode,
                                                    country
                                                })
                                            })
                                        } else if(typeof assigneeList === "object") {
                                            let addressBook = ''
                                            if(assigneeList.hasOwnProperty('addressbook')) {
                                                addressBook = assigneeList.addressbook
                                            } else {
                                                addressBook = assigneeList
                                            }
                                            console.log('addressBook', addressBook)
                                            if(addressBook.hasOwnProperty('orgname')) {
                                                originalName = addressBook['orgname']
                                            }
                                            if(addressBook.hasOwnProperty('address')) {
                                                const address = addressBook['address']
                                                address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                city = address.hasOwnProperty('city') ? address['city'] : ''
                                                state = address.hasOwnProperty('state') ? address['state'] : ''
                                                postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                country = address.hasOwnProperty('country') ? address['country'] : ''
                                            }

                                            assignees.push({
                                                appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],
                                                original_name: originalName,
                                                address_1: address1,
                                                address_2: address2,
                                                city,
                                                state,
                                                postalcode,
                                                country,
                                            })
                                        }
                                    } else {
                                        console.log('No assignee found')
                                    }
                                } else if(usBibliographic.hasOwnProperty('us-parties')) {
                                    const applicants = usBibliographic['us-parties']['us-applicants']['us-applicant']
                                    console.log(applicants.length, JSON.stringify(applicants))                          
                                    if(Array.isArray(applicants) && applicants.length > 0) {
                                        applicants.forEach( app => {
                                            if(app['@_app-type'] == 'applicant') {
                                                const addressBook = app.addressbook

                                                if(addressBook.hasOwnProperty('orgname')) {
                                                    originalName = addressBook['orgname']
                                                }
                                                if(addressBook.hasOwnProperty('address')) {
                                                    if(originalName != '') {
                                                        const address = addressBook['address']
                                                        address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                        address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                        city = address.hasOwnProperty('city') ? address['city'] : ''
                                                        state = address.hasOwnProperty('state') ? address['state'] : ''
                                                        postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                        country = address.hasOwnProperty('country') ? address['country'] : ''
                                                    }
                                                }
                                                
                                                if(addressBook.hasOwnProperty('first-name') || addressBook.hasOwnProperty('last-name')) {
                                                    console.log('family', addressBook['first-name'])
                                                    familyName = addressBook.hasOwnProperty('last-name') ? addressBook['last-name'] : '';
                                                    givenName = addressBook.hasOwnProperty('first-name') ? addressBook['first-name'] : '';
                                                    middleName = addressBook.hasOwnProperty('middle-name') ?  addressBook['middle-name'] : '';
                                                    type = 1;
                                                }
                                                applicant.push({
                                                    appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                    publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],
                                                    original_name: originalName,
                                                    address_1: address1,
                                                    address_2: address2,
                                                    city,
                                                    state,
                                                    postalcode,
                                                    country,
                                                    family_name: familyName,
                                                    given_name: givenName,
                                                    middle_name: middleName,
                                                    type,
                                                    file_name: fileName
                                                })
                                            }
                                        })
                                    } else if(typeof applicants === "object") {
                                        if(applicants['@_app-type'] == 'applicant') {
                                            const addressBook = applicants.addressbook

                                            if(addressBook.hasOwnProperty('orgname')) {
                                                originalName = addressBook['orgname']
                                            }

                                            if(originalName != '') {
                                                const address = addressBook['address']
                                                address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                city = address.hasOwnProperty('city') ? address['city'] : ''
                                                state = address.hasOwnProperty('state') ? address['state'] : ''
                                                postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                country = address.hasOwnProperty('country') ? address['country'] : ''
                                            }
                                            if(addressBook.hasOwnProperty('first-name') || addressBook.hasOwnProperty('last-name')) {
                                                console.log('family', addressBook['first-name'])
                                                familyName = addressBook.hasOwnProperty('last-name') ? addressBook['last-name'] : '';
                                                givenName = addressBook.hasOwnProperty('first-name') ? addressBook['first-name'] : '';
                                                middleName = addressBook.hasOwnProperty('middle-name') ?  addressBook['middle-name'] : '';
                                                type = 1;
                                            }
                                            applicant.push({
                                                appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],
                                                original_name: originalName,
                                                address_1: address1,
                                                address_2: address2,
                                                city,
                                                state,
                                                postalcode,
                                                country,
                                                family_name: familyName,
                                                given_name: givenName,
                                                middle_name: middleName,
                                                type,
                                                file_name: fileName
                                            })
                                        }
                                    }
                                    

                                    if(usBibliographic.hasOwnProperty('assignees')){
                                        const assigneeList = usBibliographic.assignees.assignee
                                    
                                        if(Array.isArray(assigneeList) && assigneeList.length > 0) {
                                            assigneeList.forEach( applicant => {
                                                const addressBook = applicant.addressbook
    
                                                if(addressBook.hasOwnProperty('orgname')) {
                                                    originalName = addressBook['orgname']
                                                }
                                                if(addressBook.hasOwnProperty('address')) {
                                                    const address = addressBook['address']
                                                    address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                    address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                    city = address.hasOwnProperty('city') ? address['city'] : ''
                                                    state = address.hasOwnProperty('state') ? address['state'] : ''
                                                    postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                    country = address.hasOwnProperty('country') ? address['country'] : ''
                                                }
                                                assignees.push({
                                                    appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                    publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],
                                                    original_name: originalName,
                                                    address_1: address1,
                                                    address_2: address2,
                                                    city,
                                                    state,
                                                    postalcode,
                                                    country
                                                })
                                            })
                                        } else if(typeof assigneeList === "object") {
                                            const addressBook = assigneeList.addressbook
    
                                            if(addressBook.hasOwnProperty('orgname')) {
                                                originalName = addressBook['orgname']
                                            }
    
                                            if(addressBook.hasOwnProperty('address')) {
                                                const address = addressBook['address']
                                                address1 = address.hasOwnProperty('address-1') ? address['address-1'] : ''
                                                address2 = address.hasOwnProperty('address-2') ? address['address-2'] : ''
                                                city = address.hasOwnProperty('city') ? address['city'] : ''
                                                state = address.hasOwnProperty('state') ? address['state'] : ''
                                                postalcode = address.hasOwnProperty('postalcode') ? address['postalcode'] : ''
                                                country = address.hasOwnProperty('country') ? address['country'] : ''
                                            }
                                            assignees.push({
                                                appno_doc_num:  applicationReference.hasOwnProperty('document-id') ? applicationReference['document-id']['doc-number']: applicationReference['application-number']['doc-number'],
                                                publication_number: publicationReference.hasOwnProperty('document-id') ? publicationReference['document-id']['doc-number'] : publicationReference['doc-number'],
                                                original_name: originalName,
                                                address_1: address1,
                                                address_2: address2,
                                                city,
                                                state,
                                                postalcode,
                                                country,
                                            })
                                        }
                                    } else {
                                        console.log('NO Assignee')
                                    }
                                }
                                resolve('read')
                            }
                        }                
                    } else {
                        let findIndex = data.toString().indexOf('<PATDOC')
                        if(findIndex !== -1) {                
                            let xmlData = data.toString().substring(findIndex, data.length)
                            /**
                            * Remove <B500> This node has lots of childeren open without closing 
                             */
                            const findIndexOpen500 = xmlData.toString().indexOf('<B500>')
                            const findIndexClosing500 = xmlData.toString().indexOf('</B500>')

                            xmlData = xmlData.substring(0, findIndexOpen500) + xmlData.substring(findIndexClosing500 + 7, xmlData.length)
                            if(parser.validate(xmlData)) {
                                const xmlToJSON = parser.parse( xmlData, {ignoreAttributes: false});
                                //console.log(JSON.stringify(xmlToJSON))
                                
                                
                            }
                        } else {
                            reject('inventor not found')
                        }
                    }
                });
                
                promise
                .then(async () => {
                    console.log(assignees);
                    console.log(applicant);
                    /*
                    if(assignees.length > 0 ) {
                        await AssigneeGrant.bulkCreate(assignees,  {ignoreDuplicates: true})
                    } else {
                        console.log(`No assignees - ${fullPath}`);
                    }
                    if(applicant.length > 0 ) {
                        await ApplicantsGrant.bulkCreate(applicant,  {ignoreDuplicates: true})
                    } else {
                        console.log(`No applicant - ${fullPath}`);
                    }*/


                    const nextIndex = fileIndex + 1;
                    if(nextIndex < allFiles.length) {
                        readFile(nextIndex)
                    }
                }) 
                .catch((error) => {
                    console.log(`Eror message - ${error} - ${fullPath}`);
                    const nextIndex = fileIndex + 1;
                    if(nextIndex < allFiles.length) {
                        readFile(nextIndex)
                    }
                }) 
            } catch(error){
                console.log(`Eror message - ${error.message} - ${fullPath}`);
                const nextIndex = fileIndex + 1;
                if(nextIndex < allFiles.length) {
                    await readFile(nextIndex)
                }
            }
        } else {
            console.log(`Eror while reading file - ${err} - ${fullPath}`);
            const nextIndex = fileIndex + 1;
            if(nextIndex < allFiles.length) {
                await readFile(nextIndex)
            }
        }
    });
}
